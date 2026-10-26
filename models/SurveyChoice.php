<?php namespace Renick\Survey\Models;

use Model;

/**
 * Model
 */
class SurveyChoice extends Model
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
    public $table = 'renick_survey_choices';

    /**
     * @var array rules for validation.
     */
    public $rules = [
        'survey_id' => 'required|integer',
        'survey_event_id' => 'required|integer',
        'option_title' => 'required|string|max:255',
    ];

    public $belongsTo = [
        'event' => [SurveyEvent::class, 'key' => 'survey_event_id'],
    ];

    public function afterSave(): void {
        Survey::flushChoicesCache((int) $this->survey_id);
    }

    public function afterDelete(): void {
        Survey::flushChoicesCache((int) $this->survey_id);
    }

    public function afterRestore(): void {
        Survey::flushChoicesCache((int) $this->survey_id);
    }

    public function getEvent(): ?SurveyEvent {
        return $this->event;
    }
}
