<?php namespace Renick\Survey\Classes\Content;

use Renick\Survey\Models\Survey;

/**
 * Signed parameters of one embedded survey. The signature binds the survey reference (uuid or legacy id) to the block
 * settings, so a page cannot be used to probe arbitrary ids or to widen the results policy.
 * It replaces neither the CSRF check nor the checks on the current survey record.
 */
final class EmbedPayload
{
    public const RESULTS_INHERIT = 'inherit';
    public const RESULTS_NEVER = 'never';

    public function __construct(
        int|string $ref,
        public readonly string $results = self::RESULTS_INHERIT,
        public readonly int $headingLevel = 3,
        public readonly bool $shellHasHeading = false,
    ) {
        $this->ref = (string) $ref;
    }

    /** UUID of the survey, or the numeric id written by blocks of older versions */
    public readonly string $ref;

    /** @param array<string, mixed> $data block data */
    public static function fromBlockData(array $data): ?self
    {
        $ref = trim((string) ($data['survey_id'] ?? ''));
        if (!Survey::isValidRef($ref)) {
            return null;
        }

        return new self(
            $ref,
            ($data['results'] ?? '') === self::RESULTS_NEVER ? self::RESULTS_NEVER : self::RESULTS_INHERIT,
            self::clampLevel($data['heading_level'] ?? 3),
            trim((string) ($data['title'] ?? '')) !== '',
        );
    }

    public static function clampLevel(mixed $level): int
    {
        return max(2, min(6, (int) $level ?: 3));
    }

    public function sign(): string
    {
        return hash_hmac('sha256', $this->message(), self::key());
    }

    public function verify(string $signature): bool
    {
        return $signature !== '' && hash_equals($this->sign(), $signature);
    }

    /** @return array<string, string|int> query parameters for the embed endpoint */
    public function toQuery(): array
    {
        return [
            'r' => $this->results,
            'h' => $this->headingLevel,
            's' => $this->shellHasHeading ? 1 : 0,
            'sig' => $this->sign(),
        ];
    }

    public static function fromRequest(string $ref, array $input): self
    {
        return new self(
            $ref,
            ($input['r'] ?? '') === self::RESULTS_NEVER ? self::RESULTS_NEVER : self::RESULTS_INHERIT,
            self::clampLevel($input['h'] ?? 3),
            (string) ($input['s'] ?? '0') === '1',
        );
    }

    private function message(): string
    {
        return implode('|', ['survey-embed', $this->ref, $this->results, $this->headingLevel, $this->shellHasHeading ? 1 : 0]);
    }

    private static function key(): string
    {
        return (string) config('app.key');
    }
}
