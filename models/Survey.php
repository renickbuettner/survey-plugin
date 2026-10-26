<?php namespace Renick\Survey\Models;

use Backend;
use Cache;
use Model;
use October\Rain\Database\Relations\HasMany;
use Renick\Survey\Classes\Compat\Notifier;
use Renick\Survey\Classes\Compat\PluginConfig;
use Session;

/**
 * Model
 */
class Survey extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    public const RESULTS_AFTER_VOTE = 'after_vote';
    public const RESULTS_NEVER = 'never';
    public const RESULTS_ALWAYS = 'always';

    /**
     * @var array dates to cast from the database.
     */
    protected $dates = ['deleted_at'];

    /**
     * @var string table in the database used by the model.
     */
    public $table = 'renick_survey_surveys';

    /**
     * @var array rules for validation.
     */
    public $rules = [
        'title' => 'required|string|max:255',
        'notification_to' => 'nullable|email|max:255',
        'results_mode' => 'nullable|in:after_vote,never,always',
    ];

    /**
     * @var array jsonable attributes.
     */
    public $jsonable = [
        'meta',
        'options'
    ];

    /**
     * @var array relations
     */
    public $hasMany = [
        'events' => SurveyEvent::class,
        'choices' => SurveyChoice::class,
    ];


    public static function isValidRef(string $ref): bool
    {
        return $ref !== '' && (ctype_digit($ref) ? (int) $ref > 0 : (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $ref));
    }

    /**
     * Finds a survey by its UUID. A plain number is the id that blocks of older versions stored.
     */
    public static function findByRef(string $ref): ?self
    {
        if (!self::isValidRef($ref)) {
            return null;
        }

        return ctype_digit($ref)
            ? static::query()->find((int) $ref)
            : static::query()->where('uuid', strtolower($ref))->first();
    }

    public function beforeCreate(): void
    {
        if (!$this->uuid || static::withTrashed()->where('uuid', $this->uuid)->exists()) {
            $this->uuid = (string) \Illuminate\Support\Str::uuid();
        }
    }

    public function events(): HasMany {
        return $this->hasMany(SurveyEvent::class);
    }

    public function getEventCount(): int {
        return $this->events()->count();
    }

    public function choices(): HasMany {
        return $this->hasMany(SurveyChoice::class);
    }

    public function getChoicesCount(): int {
        return $this->choices()->count();
    }

    public static function totalChoicesCacheKey(int $surveyId): string {
        return "SURVEY_TOTAL_CHOICES_{$surveyId}";
    }

    /**
     * Drop the cached choice totals, e.g. after a new event or a deletion.
     */
    public static function flushChoicesCache(int $surveyId): void {
        Cache::forget(self::totalChoicesCacheKey($surveyId));
    }

    /**
     * Choice totals grouped by option (aggregated in SQL, cached until the
     * next change of a choice, see SurveyChoice model events).
     *
     * @param bool $force bypass and refresh the cache
     */
    public function getTotalChoices($force = false): array {
        $surveyId = (int) $this->id;
        $key = self::totalChoicesCacheKey($surveyId);

        if ($force) {
            Cache::forget($key);
        }

        return Cache::remember($key, 60 * 60, function () use ($surveyId) {
            return SurveyChoice::where('survey_id', $surveyId)
                ->whereIn('survey_event_id', SurveyEvent::where('survey_id', $surveyId)->select('id'))
                ->groupBy('option_title')
                ->selectRaw('option_title, count(*) as total')
                ->orderBy('total', 'desc')
                ->orderBy('option_title')
                ->get()
                ->toArray();
        });
    }

    /**
     * A survey accepts a submission unless the session or IP limit is hit.
     * A SURVEY_IP_ADDRESS_LIMIT of 0 (or lower) disables the IP limit.
     */
    public function isAccessible(): bool {
        if (PluginConfig::bool('cookie_limit') && Session::has("renick_survey_{$this->id}")) {
            return false;
        }

        $ipAddressLimit = PluginConfig::int('ip_address_limit');
        if ($ipAddressLimit <= 0) {
            return true;
        }

        return SurveyEvent::getIpAddressSubmissionsCount($this->id, (string) request()->ip()) < $ipAddressLimit;
    }

    public function getResultsModeOptions(): array {
        return [
            self::RESULTS_AFTER_VOTE => trans('renick.survey::lang.survey.results_mode_after_vote'),
            self::RESULTS_NEVER => trans('renick.survey::lang.survey.results_mode_never'),
            self::RESULTS_ALWAYS => trans('renick.survey::lang.survey.results_mode_always'),
        ];
    }

    /**
     * The effective mode; unknown or empty values behave like the default.
     */
    public function getEffectiveResultsMode(): string {
        $mode = (string) $this->results_mode;
        return in_array($mode, [self::RESULTS_NEVER, self::RESULTS_ALWAYS], true) ? $mode : self::RESULTS_AFTER_VOTE;
    }

    /**
     * Whether the public totals may be shown to a visitor.
     *
     * @param bool $hasVoted the visitor voted already (or cannot vote anymore)
     */
    public function resultsVisible(bool $hasVoted): bool {
        return match ($this->getEffectiveResultsMode()) {
            self::RESULTS_NEVER => false,
            self::RESULTS_ALWAYS => true,
            default => $hasVoted,
        };
    }

    /**
     * Rich text authored in the backend, cleaned before it is printed unescaped.
     */
    public function getDescriptionHtml(): string {
        return \Html::clean((string) $this->description);
    }

    public static function getOptionHtml(array $option): string {
        return \Html::clean((string) ($option['text'] ?? ''));
    }

    public function getAnswerByTitle(string $title): mixed {
        foreach ((array) $this->options as $option)
            if (($option['title'] ?? null) === $title)
                return $option;
        return null;
    }

    public function getUpdateLinkAttribute(): array
    {
        return [
            Backend::url("renick/survey/surveycontroller/update/{$this->id}"),
            trans('renick.survey::lang.survey.update')
        ];
    }

    /**
     * Send notification to admin
     * @param SurveyEvent $event
     * @return void
     */
    public function onEventCreated(SurveyEvent $event): void {
        $to = $this->notification_to ?? '';
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || PluginConfig::bool('disable_email_notifications'))
            return;

        $subject = trans('renick.survey::lang.mail.admin_notification.title');
        $params = [
            'title' => $this->title,
            'is_anonym' => !!$this->is_anonym,
            'choices' => $event->choices()->pluck('option_title')->toArray(),
            'columns' => [],
        ];

        if (!$this->is_anonym) {
            $params['columns'][trans('renick.survey::lang.mail.admin_notification.user_name')] = $event->user_name;
            $params['columns'][trans('renick.survey::lang.mail.admin_notification.user_email')] = $event->user_email;
            $params['columns'][trans('renick.survey::lang.mail.admin_notification.user_phone')] = $event->user_meta['user_phone'] ?? '';

            if (is_array($event->user_meta) && isset($event->user_meta['user_comment']))
                $params['columns'][trans('renick.survey::lang.mail.admin_notification.user_comment')] = $event->user_meta['user_comment'] ?? '';

            if (intval($event->submit_duration) > 0)
                $params['columns'][trans('renick.survey::lang.mail.admin_notification.submit_duration')] = $event->submit_duration;
        }

        Notifier::send('renick.survey::mail.admin_notification', $params, $to, $subject);
    }

    /**
     * To be used within October forms (e.g. dropdown)
     * @return array
     */
    public static function getSurveyOptions(): array {
        return self::query()->pluck('title', 'id')->all();
    }

}
