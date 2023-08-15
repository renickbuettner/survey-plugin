<?php namespace Renick\Survey\Controllers;

use Backend;
use BackendMenu;
use Backend\Classes\Controller;

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

}
