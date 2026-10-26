<?php namespace Renick\Survey\Classes\Content;

use Renick\Content\Classes\RecordSource;
use Renick\Survey\Models\Survey;

/** Record picker backend of the survey block: searches surveys by title or id */
final class SurveySource extends RecordSource
{
    public function search(string $query, int $limit): array
    {
        $builder = Survey::query();
        $query = trim($query);
        if ($query !== '') {
            self::matchWords($builder, $query, ['title'], 'title', 'title');
        } else {
            $builder->orderBy('title');
        }

        return $builder->limit(max(1, min($limit, self::MAX_RESULTS)))->get()->map(fn (Survey $s) => self::row($s))->all();
    }

    public function labels(array $ids): array
    {
        $refs = array_values(array_unique(array_filter(array_map('strval', $ids), [Survey::class, 'isValidRef'])));
        if ($refs === []) {
            return [];
        }

        // numeric references are written by blocks of older versions
        $uuids = array_values(array_filter($refs, fn ($r) => !ctype_digit($r)));
        $legacy = array_values(array_filter($refs, 'ctype_digit'));

        return Survey::query()
            ->where(function ($q) use ($uuids, $legacy) {
                $q->whereIn('uuid', array_map('strtolower', $uuids))->orWhereIn('id', $legacy);
            })
            ->get()
            ->map(fn (Survey $s) => self::row($s, in_array((string) $s->id, $legacy, true)))
            ->all();
    }

    private static function row(Survey $survey, bool $legacyRef = false): array
    {
        return [
            'id' => $legacyRef ? (string) $survey->id : $survey->uuid,
            'label' => (string) $survey->title,
            'hint' => trans($survey->is_active ? 'renick.survey::lang.content_block.hint_active' : 'renick.survey::lang.content_block.hint_inactive')
                . ' · #' . $survey->id,
        ];
    }
}
