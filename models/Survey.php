<?php namespace Renick\Survey\Models;

use Backend;
use Mail;
use Model;
use October\Rain\Database\Relations\HasMany;
use Session;

/**
 * Model
 */
class Survey extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

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
    public $rules = [];

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

    public function getTotalChoices($force = false): array {
        $key = "SURVEY_TOTAL_CHOICES_{$this->id}";

        if (\Cache::has($key) && !$force)
            return \Cache::get($key);

        $array = SurveyChoice::where('survey_id', $this->id)
            ->groupBy('option_title')
            ->selectRaw('option_title, count(*) as total')
            ->orderBy('total', 'desc')
            ->get()
            ->toArray();

        \Cache::forever($key, $array);
        return $array;
    }

    public function isAccessible(): bool {
        $cookieName = "renick_survey_{$this->id}";
        $ipAddress = request()->ip();
        $isCookieAlreadySet = env('SURVEY_COOKIE_LIMIT', true) && Session::has($cookieName);
        $idAddressLimit = intval(env('SURVEY_IP_ADDRESS_LIMIT', 1));
        if ($isCookieAlreadySet || SurveyEvent::getIpAddressSubmissionsCount($this->id, $ipAddress) >= $idAddressLimit)
            return false;
        return true;
    }

    public function getAnswerByTitle(string $title): mixed {
        foreach ($this->options as $option)
            if ($option['title'] === $title)
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
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || env('SURVEY_DISABLE_EMAIL_NOTIFICATIONS', false))
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
            $params['columns'][trans('renick.survey::lang.mail.admin_notification.user_phone')] = $event->user_phone;

            if (is_array($event->user_meta) && isset($event->user_meta['user_comment']))
                $params['columns'][trans('renick.survey::lang.mail.admin_notification.user_comment')] = $event->user_meta['user_comment'] ?? '';

            if (intval($event->submit_duration) > 0)
                $params['columns'][trans('renick.survey::lang.mail.admin_notification.submit_duration')] = $event->submit_duration;
        }

        Mail::queue('renick.survey::mail.admin_notification', $params, function ($message) use ($to, $subject) {
            $message->to($to, $name = null);
            $message->subject($subject);
        });
    }

    /**
     * To be used within October forms (e.g. dropdown)
     * @return array
     */
    public static function getSurveyOptions(): array {
        return self::all()
            ->pluck('title', 'id')
            ->toArray();
    }

}
