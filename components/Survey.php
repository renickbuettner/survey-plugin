<?php namespace Renick\Survey\Components;

use AjaxException;
use Cms\Classes\ComponentBase;
use Cookie;
use Renick\Survey\Models\SurveyChoice;
use Renick\Survey\Models\SurveyEvent;
use Request;
use Session;
use Str;

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
        $plucked = \Renick\Survey\Models\Survey::all()->pluck(
            'title',
            'id'
        );

        return $plucked->all();
    }

    public function getSurvey(): ?\Renick\Survey\Models\Survey {
        $survey = \Renick\Survey\Models\Survey::find($this->property('surveyId'));
        if ($survey)
            // save timestamp to session, to measure the time it takes to submit the survey
            Session::put("survey_submit_start_{$survey->id}", microtime(true));
        return $survey;
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

        try {
            if (!is_array($params) || count($params) === 0)
                throw new \Exception('Invalid request');

            $surveyId = array_keys($params)[0];
            $survey = \Renick\Survey\Models\Survey::findOrFail($surveyId);
            $this->choices = $params[$surveyId] ?? [];

            if ($survey->id !== $this->getSurvey()->id)
                throw new \Exception('Mismatch in survey id');

            $cookieName = "renick_survey_{$survey->id}";
            $ipAddress = request()->ip();
            if (!$survey->isAccessible()) {
                $this->error = trans('renick.survey::lang.component.error_already_submitted');
                return [
                    'isSuccess' => false,
                    'error' => trans('renick.survey::lang.component.error_already_submitted'),
                ];
            }

            // 1. Create event
            $event = new SurveyEvent();
            $event->survey_id = $survey->id;
            $event->ip_address = $ipAddress;
            // ToDo: validate domain to prevent bounces?
            // see: https://stackoverflow.com/questions/12026842/how-to-validate-an-email-address-in-php
            if (filter_var(input('user_email'), FILTER_VALIDATE_EMAIL))
                $event->user_email = input('user_email') ?? null;
            $event->user_name = input('user_name') ?? null;
            $event->user_meta = [
                'user_phone' => input('user_phone') ?? null,
                'user_comment' => Str::limit(strip_tags(input('user_comment') ?? ''), 2000),
            ];

            $durationKey = "survey_submit_start_{$survey->id}";
            if (Session::has($durationKey))
                $event->submit_duration = round(microtime(true) - Session::pull($durationKey, 0), 3);

            $event->save();
            Session::put($cookieName, true, 60 * 24 * 365);

            // 2. Store the choices belonging to the event
            $eventId = $event->id;
            foreach ($this->choices as $choice) {
                // validate that answer actually is preset in backend
                if ($survey->getAnswerByTitle($choice) === null)
                    continue;

                $eventChoice = new SurveyChoice();
                $eventChoice->survey_id = $survey->id;
                $eventChoice->survey_event_id = $eventId;
                $eventChoice->option_title = $choice;
                $eventChoice->save();
            }

            // 3. If needed: Fetch the choices by group for the chart
            $this->choices = $survey->getTotalChoices(true);

            // 4. Send notification to admin
            $survey->onEventCreated($event);

        } catch (\Exception $e) {
            $this->error = trans('renick.survey::lang.component.error_invalid_request');
            throw new AjaxException([
                'isSuccess' => false,
                'error' => $this->error,
            ]);
        }

        $this->isSuccess = $this->error === null;
        if ($this->isSuccess)
            $this->feedback = trans('renick.survey::lang.component.feedback_success');

        return [
            'isSuccess' => $this->isSuccess,
            'error' => $this->error,
            'choices' => $this->choices,
        ];
    }
}
