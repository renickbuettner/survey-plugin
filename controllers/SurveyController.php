<?php namespace Renick\Survey\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use Renick\Survey\Classes\Export\ResultsExporter;
use Renick\Survey\Models\Survey;
use Renick\Survey\Models\SurveyChoice;
use Renick\Survey\Models\SurveyEvent;


class SurveyController extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = [
        'renick_survey_manage'
    ];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Renick.Survey', 'renick-survey');
    }

    public function getSurveyCount(): int {
        return Survey::count();
    }

    public function getSurveyEventCount(): int {
        return SurveyEvent::count();
    }

    public function getSurveyChoicesCount(): int {
        return SurveyChoice::count();
    }

    public function results(int $id): void
    {
        $this->vars['survey'] = Survey::findOrFail($id);
        $this->vars['survey_events'] = SurveyEvent::getUniqueEvents($id)->get();
        $eventIds = $this->vars['survey_events']->pluck('id');
        $this->vars['survey_choices'] = SurveyChoice::where('survey_id', $id)->whereIn('survey_event_id', $eventIds)->with('event')->get();

        $this->pageTitle = 'renick.survey::lang.survey.results';
        $this->makeView('results');
    }

    /**
     * Downloads the survey results as Excel file (csv fallback without ZipArchive).
     *
     * @param string|int $id
     */
    public function export($id = null)
    {
        if (!is_scalar($id) || !ctype_digit((string)$id)) {
            abort(404);
        }

        $survey = Survey::find((int)$id);
        if (!$survey) {
            abort(404);
        }

        $file = (new ResultsExporter($survey))->build();

        return response()
            ->download($file['path'], $file['filename'], ['Content-Type' => $file['mime']])
            ->deleteFileAfterSend(true);
    }

}
