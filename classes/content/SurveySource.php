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
        $ids = self::intIds($ids);
        if ($ids === []) {
            return [];
        }

        return Survey::query()->whereIn('id', $ids)->get()->map(fn (Survey $s) => self::row($s))->all();
    }

    private static function row(Survey $survey): array
    {
        return [
            'id' => (string) $survey->id,
            'label' => (string) $survey->title,
            'hint' => trans($survey->is_active ? 'renick.survey::lang.content_block.hint_active' : 'renick.survey::lang.content_block.hint_inactive')
                . ' · #' . $survey->id,
        ];
    }
}
