<?php namespace Renick\Survey\Models;

use Model;

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


    public static function getIpAddressSubmissionsCount(int $surveyId, string $ipAddress): int {
        return self::where('ip_address', $ipAddress)
            ->where('survey_id', $surveyId)
            ->count();
    }

}
