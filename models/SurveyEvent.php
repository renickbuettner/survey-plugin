<?php namespace Renick\Survey\Models;

use Model;
use October\Rain\Database\Relations\HasMany;

/**
 * Model
 */
class SurveyEvent extends Model
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
    public $table = 'renick_survey_events';

    /**
     * @var array rules for validation.
     */
    public $rules = [
    ];

    public $jsonable = [
        'user_meta',
    ];

    /**
     * @var array relations
     */
    public $hasMany = [
        'choices' => SurveyChoice::class,
    ];

    public function choices(): HasMany {
        return $this->hasMany(SurveyChoice::class);
    }

    public static function getIpAddressSubmissionsCount(int $surveyId, string $ipAddress): int {
        return self::where('ip_address', $ipAddress)
            ->where('survey_id', $surveyId)
            ->count();
    }

}
