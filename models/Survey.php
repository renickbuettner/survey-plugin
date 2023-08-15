<?php namespace Renick\Survey\Models;

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
        'events' => SurveyEvent::class
    ];


    public function events(): HasMany
    {
        return $this->hasMany(SurveyEvent::class, 'id', 'survey_id');
    }

    public function getEventCount(): int {
        return $this->events()->count();
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
        $isCookieAlreadySet = env('SURVEY_COOKIE_LIMIT', true) && Session::has($cookieName, false);
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

}