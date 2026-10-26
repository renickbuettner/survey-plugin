<?php namespace Renick\Survey\Classes\Content;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Renick\Survey\Classes\SubmissionResult;
use Renick\Survey\Classes\SubmissionService;
use Renick\Survey\Models\Survey;
use Session;

/**
 * Endpoints of the survey block: GET returns the per-visitor fragment, POST stores a vote.
 * Both answers are private and never cached, they depend on the session of the visitor.
 */
final class EmbedController
{
    private const HEADERS = ['Cache-Control' => 'private, no-store, max-age=0', 'Vary' => 'Cookie'];

    public function show(Request $request, string $id): Response
    {
        $payload = $this->payload($request, $id);
        if ($payload === null) {
            return response('', 404, self::HEADERS);
        }

        $survey = Survey::findByRef($id);
        $renderer = new EmbedRenderer($payload);

        // Measures the time to submit; stays untouched on reloads and for further instances on the page
        $timerKey = SubmissionService::sessionTimerKey((int) $survey?->id);
        if ($survey && $renderer->canVote($survey) && !Session::has($timerKey)) {
            Session::put($timerKey, microtime(true));
        }

        return response($renderer->render($survey), 200, self::HEADERS + ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function submit(Request $request, string $id): JsonResponse
    {
        // October's web group does not verify CSRF tokens by itself
        $token = (string) $request->input('_token', '');
        if ($token === '' || !hash_equals((string) Session::token(), $token)) {
            return $this->error('renick.survey::lang.component.error_invalid_request', 419);
        }

        $payload = $this->payload($request, $id);
        $survey = $payload ? Survey::findByRef($id) : null;
        if (!$payload || !$survey) {
            return $this->error('renick.survey::lang.component.error_invalid_request', 404);
        }

        // Anonymous surveys collect no personal data, so no privacy consent is asked for
        if (!$survey->is_anonym && !$request->boolean('user_privacy')) {
            return $this->error('renick.survey::lang.component.error_invalid_request', 422);
        }

        $result = (new SubmissionService())->submit($survey, $request->input('choices'), $request->all());
        $renderer = new EmbedRenderer($payload);

        return match ($result->status) {
            SubmissionResult::OK => response()->json([
                'ok' => true,
                'html' => $renderer->render($survey, trans('renick.survey::lang.component.feedback_success'), true),
            ], 200, self::HEADERS),
            SubmissionResult::THROTTLED => $this->error('renick.survey::lang.component.error_too_many_requests', 429),
            SubmissionResult::REJECTED => $this->error('renick.survey::lang.component.error_already_submitted', 409, $renderer->render($survey)),
            default => $this->error('renick.survey::lang.component.error_invalid_request', 422),
        };
    }

    private function payload(Request $request, string $id): ?EmbedPayload
    {
        $payload = EmbedPayload::fromRequest($id, $request->all());

        return $payload->verify((string) $request->input('sig', '')) ? $payload : null;
    }

    private function error(string $key, int $status, ?string $html = null): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => trans($key), 'html' => $html], $status, self::HEADERS);
    }
}
