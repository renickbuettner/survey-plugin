<?php namespace Renick\Survey\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
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
        return Survey::all()->count();
    }

    public function getSurveyEventCount(): int {
        return SurveyEvent::all()->count();
    }

    public function getSurveyChoicesCount(): int {
        return SurveyChoice::all()->count();
    }

    public function results(int $id): void
    {
        $this->vars['survey'] = Survey::findOrFail($id);
        $this->vars['survey_events'] = SurveyEvent::where('survey_id', $id)->get();
        $this->vars['survey_choices'] = SurveyChoice::where('survey_id', $id)->get();

        $this->pageTitle = 'renick.survey::lang.survey.results';
        $this->makeView('results');
    }

}
