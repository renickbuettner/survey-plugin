<?php namespace Renick\Survey\Components;

use AjaxException;
use Cms\Classes\ComponentBase;
use DB;
use Illuminate\Support\Facades\RateLimiter;
use Renick\Survey\Classes\Compat\PluginConfig;
use Renick\Survey\Models\SurveyChoice;
use Renick\Survey\Models\SurveyEvent;
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
            Session::put("survey_submit_start_{$survey->id}", microtime(true));
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
        if (!is_array($params) || !$survey || !isset($params[$survey->id]) || !$survey->is_active) {
            $this->failure();
            return [];
        }

        // Throttle, independent of the (configurable) submission limits
        $throttleKey = "renick_survey_submit_{$survey->id}_" . request()->ip();
        $maxAttempts = PluginConfig::int('throttle_per_minute');
        if ($maxAttempts > 0) {
            if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
                $this->failure('renick.survey::lang.component.error_too_many_requests');
            }
            RateLimiter::hit($throttleKey, 60);
        }

        // Only known answers, each at most once, a single one for single-select surveys
        $selected = [];
        foreach ((array) $params[$survey->id] as $choice) {
            if (is_string($choice) && $survey->getAnswerByTitle($choice) !== null) {
                $selected[$choice] = $choice;
            }
        }
        $selected = array_values($selected);
        if (count($selected) === 0 || (!$survey->is_multiselect && count($selected) > 1)) {
            $this->failure();
        }

        $email = trim((string) input('user_email', ''));
        $hasValidEmail = $email !== '' && \Validator::make(['email' => $email], ['email' => 'email'])->passes();
        $name = trim(strip_tags((string) input('user_name', '')));
        if (!$survey->is_anonym && ($name === '' || !$hasValidEmail)) {
            $this->failure();
        }

        try {
            $event = $this->storeSubmission($survey, $selected, $name, $hasValidEmail ? $email : null);
        } catch (\Throwable $e) {
            \Log::error('Renick.Survey: submission failed: ' . $e->getMessage());
            $this->failure();
        }

        if ($event === null) {
            $this->error = trans('renick.survey::lang.component.error_already_submitted');
            return [
                'isSuccess' => false,
                'error' => $this->error,
            ];
        }

        Session::put("renick_survey_{$survey->id}", true);
        $this->choices = $survey->getTotalChoices(true);
        $survey->onEventCreated($event);

        $this->isSuccess = true;
        $this->feedback = trans('renick.survey::lang.component.feedback_success');

        return [
            'isSuccess' => true,
            'error' => null,
            'choices' => $this->choices,
        ];
    }

    /**
     * Stores event and choices atomically. The survey row is locked, so two
     * parallel requests cannot both pass the limit checks.
     *
     * @return SurveyEvent|null null if the survey is not accessible (anymore)
     */
    protected function storeSubmission(\Renick\Survey\Models\Survey $survey, array $selected, string $name, ?string $email): ?SurveyEvent
    {
        return DB::transaction(function () use ($survey, $selected, $name, $email) {
            \Renick\Survey\Models\Survey::query()->whereKey($survey->id)->lockForUpdate()->first();

            $isUniqueEmail = !PluginConfig::bool('unique_email') || SurveyEvent::isUniqueEmail($survey->id, $email);
            if (!$survey->isAccessible() || !$isUniqueEmail) {
                return null;
            }

            $event = new SurveyEvent();
            $event->survey_id = $survey->id;
            $event->ip_address = request()->ip();
            $event->user_email = $email;
            $event->user_name = $name !== '' ? Str::limit($name, 255, '') : null;
            $event->user_meta = [
                'user_phone' => Str::limit(strip_tags((string) input('user_phone', '')), 50, '') ?: null,
                'user_comment' => Str::limit(strip_tags((string) input('user_comment', '')), 2000),
            ];

            $durationKey = "survey_submit_start_{$survey->id}";
            if (Session::has($durationKey)) {
                $event->submit_duration = (int) round(microtime(true) - (float) Session::pull($durationKey, 0));
            }

            $event->save();

            foreach ($selected as $title) {
                $choice = new SurveyChoice();
                $choice->survey_id = $survey->id;
                $choice->survey_event_id = $event->id;
                $choice->option_title = $title;
                $choice->save();
            }

            return $event;
        });
    }
}
