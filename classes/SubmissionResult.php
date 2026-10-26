<?php namespace Renick\Survey\Classes;

use Renick\Survey\Models\SurveyEvent;

/**
 * Outcome of SubmissionService::submit().
 */
class SubmissionResult
{
    public const OK = 'ok';
    public const INVALID = 'invalid';
    public const THROTTLED = 'throttled';
    public const REJECTED = 'rejected';

    public function __construct(
        public readonly string $status,
        public readonly ?SurveyEvent $event = null,
    ) {
    }

    public function isOk(): bool
    {
        return $this->status === self::OK;
    }
}
