<?php namespace Renick\Survey\Classes\Content;

use Renick\Survey\Models\Survey;

/**
 * Adds what the block partial needs to the render copy of a survey block. Nothing here depends on the
 * visitor, so the rendered shell stays shareable by the render and page cache.
 */
final class BlockData
{
    public const ENDPOINT = 'renick-survey/embed';
    public const SCRIPT = 'plugins/renick/survey/assets/js/survey-embed.js';
    public const STYLE = 'plugins/renick/survey/assets/css/survey-embed.css';

    public static function endpoint(int $surveyId): string
    {
        return url(self::ENDPOINT . '/' . $surveyId);
    }

    /** @param array<string, mixed> $block */
    public static function enrich(array &$block, bool $editorPreview): void
    {
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];
        $payload = EmbedPayload::fromBlockData($data);
        $survey = $payload ? Survey::query()->find($payload->surveyId) : null;

        $info = ['valid' => $payload !== null && $survey !== null];

        if ($info['valid']) {
            $info += [
                'id' => $payload->surveyId,
                'endpoint' => self::endpoint($payload->surveyId),
                'query' => http_build_query($payload->toQuery()),
                'script' => url(self::SCRIPT),
                'style' => url(self::STYLE),
                'level' => $payload->headingLevel,
                'heading' => trim((string) ($data['title'] ?? '')),
                'loading' => trans('renick.survey::lang.content_block.loading'),
                'noscript' => trans('renick.survey::lang.content_block.noscript'),
            ];
        }

        if ($editorPreview) {
            $info['preview'] = $survey ? [
                'title' => (string) $survey->title,
                'active' => (bool) $survey->is_active,
                'multiselect' => (bool) $survey->is_multiselect,
                'options' => array_values(array_filter(array_map(
                    fn ($option) => (string) ($option['title'] ?? ''),
                    (array) $survey->options
                ), 'strlen')),
                'status' => trans($survey->is_active ? 'renick.survey::lang.content_block.hint_active' : 'renick.survey::lang.content_block.hint_inactive'),
            ] : null;
            $info['missing'] = trans('renick.survey::lang.content_block.missing');
        }

        $block['data'] = $data;
        $block['data']['_survey'] = $info;
    }
}
