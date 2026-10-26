<?php namespace Renick\Survey\Components;

use AjaxException;
use Cms\Classes\ComponentBase;
use Renick\Survey\Classes\SubmissionResult;
use Renick\Survey\Classes\SubmissionService;
use Session;

/**
 * Survey Component
 *
 * @link https://docs.octobercms.com/3.x/extend/cms-components.html
 */
class Survey extends ComponentBase
{
    public mixed $error;
    public mixed $isSuccess;
    public mixed $choices;
    public string $feedback;

    public function componentDetails()
    {
        return [
            'name' => 'renick.survey::lang.component.name',
            'description' => 'renick.survey::lang.component.description'
        ];
    }

    /**
     * @link https://docs.octobercms.com/3.x/element/inspector-types.html
     */
    public function defineProperties()
    {
        return [
            'surveyId' => [
                'title' => 'renick.survey::lang.component.name',
                'type' => 'dropdown',
                'placeholder' => '- - -',
            ]
        ];
    }

    public function getSurveyIdOptions(): array {
        return \Renick\Survey\Models\Survey::query()->pluck('title', 'id')->all();
    }

    protected function findSurvey(): ?\Renick\Survey\Models\Survey {
        $id = $this->property('surveyId');
        return $id ? \Renick\Survey\Models\Survey::find($id) : null;
    }

    /**
     * Used when rendering the page: remembers the start time in the session,
     * to measure the time it takes to submit the survey.
     */
    public function getSurvey(): ?\Renick\Survey\Models\Survey {
        $survey = $this->findSurvey();
        if ($survey)
            Session::put(SubmissionService::sessionTimerKey($survey->id), microtime(true));
        return $survey;
    }

    protected function failure(string $messageKey = 'renick.survey::lang.component.error_invalid_request'): void
    {
        $this->error = trans($messageKey);
        throw new AjaxException([
            'isSuccess' => false,
            'error' => $this->error,
        ]);
    }

    /**
     * @throws AjaxException
     */
    public function onSubmitSurvey(): array
    {
        $params = input('survey');
        $this->error = null;
        $this->isSuccess = false;
        $this->choices = [];

        $survey = $this->findSurvey();
        if (!is_array($params) || !$survey || !isset($params[$survey->id])) {
            $this->failure();
            return [];
        }

        $result = (new SubmissionService())->submit($survey, $params[$survey->id], (array) input());

        if ($result->status === SubmissionResult::THROTTLED) {
            $this->failure('renick.survey::lang.component.error_too_many_requests');
        }

        if ($result->status === SubmissionResult::INVALID) {
            $this->failure();
        }

        if (!$result->isOk()) {
            $this->error = trans('renick.survey::lang.component.error_already_submitted');
            return [
                'isSuccess' => false,
                'error' => $this->error,
            ];
        }

        $this->choices = $survey->resultsVisible(true) ? $survey->getTotalChoices(true) : [];

        $this->isSuccess = true;
        $this->feedback = trans('renick.survey::lang.component.feedback_success');

        return [
            'isSuccess' => true,
            'error' => null,
            'choices' => $this->choices,
        ];
    }
}
