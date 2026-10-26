<?php namespace Renick\Survey\Classes;

use DB;
use Illuminate\Support\Facades\RateLimiter;
use Renick\Survey\Classes\Compat\PluginConfig;
use Renick\Survey\Models\Survey;
use Renick\Survey\Models\SurveyChoice;
use Renick\Survey\Models\SurveyEvent;
use Session;
use Str;

/**
 * Validates and stores a survey submission. Independent of the CMS component,
 * so other entry points (e.g. integrations) can reuse it.
 */
class SubmissionService
{
    public static function sessionTimerKey(int $surveyId): string
    {
        return "survey_submit_start_{$surveyId}";
    }

    /**
     * @param Survey $survey
     * @param mixed $choices raw selected option titles
     * @param array $input user_name, user_email, user_phone, user_comment
     */
    public function submit(Survey $survey, mixed $choices, array $input = []): SubmissionResult
    {
        if (!$survey->is_active) {
            return new SubmissionResult(SubmissionResult::INVALID);
        }

        if ($this->isThrottled($survey)) {
            return new SubmissionResult(SubmissionResult::THROTTLED);
        }

        // Only known answers, each at most once, a single one for single-select surveys
        $selected = [];
        foreach ((array) $choices as $choice) {
            if (is_string($choice) && $survey->getAnswerByTitle($choice) !== null) {
                $selected[$choice] = $choice;
            }
        }
        $selected = array_values($selected);
        if (count($selected) === 0 || (!$survey->is_multiselect && count($selected) > 1)) {
            return new SubmissionResult(SubmissionResult::INVALID);
        }

        $email = trim((string) ($input['user_email'] ?? ''));
        $hasValidEmail = $email !== '' && \Validator::make(['email' => $email], ['email' => 'email'])->passes();
        $name = trim(strip_tags((string) ($input['user_name'] ?? '')));
        if (!$survey->is_anonym && ($name === '' || !$hasValidEmail)) {
            return new SubmissionResult(SubmissionResult::INVALID);
        }

        try {
            $event = $this->store($survey, $selected, $name, $hasValidEmail ? $email : null, $input);
        } catch (\Throwable $e) {
            \Log::error('Renick.Survey: submission failed: ' . $e->getMessage());
            return new SubmissionResult(SubmissionResult::INVALID);
        }

        if ($event === null) {
            return new SubmissionResult(SubmissionResult::REJECTED);
        }

        Session::put("renick_survey_{$survey->id}", true);
        $survey->onEventCreated($event);

        return new SubmissionResult(SubmissionResult::OK, $event);
    }

    /**
     * Throttle, independent of the (configurable) submission limits.
     */
    protected function isThrottled(Survey $survey): bool
    {
        $maxAttempts = PluginConfig::int('throttle_per_minute');
        if ($maxAttempts <= 0) {
            return false;
        }

        $key = "renick_survey_submit_{$survey->id}_" . request()->ip();
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return true;
        }

        RateLimiter::hit($key, 60);
        return false;
    }

    /**
     * Stores event and choices atomically. The survey row is locked, so two
     * parallel requests cannot both pass the limit checks.
     *
     * @return SurveyEvent|null null if the survey is not accessible (anymore)
     */
    protected function store(Survey $survey, array $selected, string $name, ?string $email, array $input): ?SurveyEvent
    {
        return DB::transaction(function () use ($survey, $selected, $name, $email, $input) {
            Survey::query()->whereKey($survey->id)->lockForUpdate()->first();

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
                'user_phone' => Str::limit(strip_tags((string) ($input['user_phone'] ?? '')), 50, '') ?: null,
                'user_comment' => Str::limit(strip_tags((string) ($input['user_comment'] ?? '')), 2000),
            ];

            $durationKey = self::sessionTimerKey($survey->id);
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
