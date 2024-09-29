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
    public $rules = [];

    public function getEvent(): ?SurveyEvent {
        return SurveyEvent::find($this->survey_event_id);
    }
}
