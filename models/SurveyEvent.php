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
        'survey_id' => 'required|integer',
        'user_email' => 'nullable|email|max:255',
        'user_name' => 'nullable|string|max:255',
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

    public function choices(): \October\Rain\Database\Relations\HasMany {
        return $this->hasMany(SurveyChoice::class);
    }

    /**
     * Soft deleting an event removes its choices from the totals as well.
     */
    public function afterDelete(): void {
        if ($this->isSoftDelete()) {
            SurveyChoice::where('survey_event_id', $this->id)->get()->each->delete();
        }
        Survey::flushChoicesCache((int) $this->survey_id);
    }

    public function afterRestore(): void {
        SurveyChoice::onlyTrashed()->where('survey_event_id', $this->id)->get()->each->restore();
        Survey::flushChoicesCache((int) $this->survey_id);
    }

    public static function getIpAddressSubmissionsCount(int $surveyId, string $ipAddress): int {
        return self::where('ip_address', $ipAddress)
            ->where('survey_id', $surveyId)
            ->count();
    }

    public static function getUniqueEvents(int|string $id): \October\Rain\Database\Builder {
        return self::where('survey_id', $id);
    }

    public static function isUniqueEmail(int $surveyId, ?string $email): bool {
        if ($email === null || trim($email) === '') {
            return true;
        }

        return !self::where('survey_id', $surveyId)
            ->where('user_email', trim($email))
            ->exists();
    }
}
