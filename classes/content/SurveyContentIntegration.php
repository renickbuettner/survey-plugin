<?php namespace Renick\Survey\Classes\Content;

use Renick\Content\Classes\Integrations\Integration;
use Renick\Survey\Models\Survey;

/**
 * Survey block for the Renick.Content editor. The block itself is a static, cacheable shell;
 * the per-visitor form (CSRF token, voted state) is loaded by assets/js/survey-embed.js.
 */
final class SurveyContentIntegration extends Integration
{
    public const HANDLE = 'survey';
    public const SOURCE = 'survey-surveys';

    public function code(): string
    {
        return 'survey';
    }

    public function pluginCode(): ?string
    {
        return 'Renick.Survey';
    }

    // No switch in the Content settings: installed and active is enough
    public function isEnabled(): bool
    {
        return $this->isAvailable();
    }

    public function settingsFields(): array
    {
        return [];
    }

    // The shell is identical for every visitor
    public function variesPerVisitor(): bool
    {
        return false;
    }

    public function boot(): void
    {
        \Event::listen('renick.content.beforeRenderBlock', function (array &$block, array $preview = []) {
            if (($block['type'] ?? null) === self::HANDLE) {
                BlockData::enrich($block, ($preview['editor'] ?? false) === true);
            }

            return null;
        });
    }

    public function blockHandles(): array
    {
        return [self::HANDLE];
    }

    public function recordSources(): array
    {
        return [self::SOURCE => new SurveySource()];
    }

    public function blockDefinitions(): array
    {
        $l = 'renick.survey::lang.content_block.';

        return [[
            'handle' => self::HANDLE,
            'name' => $l . 'name',
            'icon' => 'icon-check-square-o',
            'group' => 'components',
            'settings' => false,
            'fields' => [
                'survey_id' => [
                    'label' => $l . 'survey',
                    'type' => 'recordpicker',
                    'source' => self::SOURCE,
                    'commentAbove' => $l . 'survey_comment',
                ],
                'title' => [
                    'label' => $l . 'title',
                    'type' => 'text',
                    'commentAbove' => $l . 'title_comment',
                ],
                'heading_level' => [
                    'label' => $l . 'heading_level',
                    'type' => 'dropdown',
                    'options' => ['2' => 'H2', '3' => 'H3', '4' => 'H4', '5' => 'H5', '6' => 'H6'],
                    'default' => '3',
                    'commentAbove' => $l . 'heading_level_comment',
                ],
                'results' => [
                    'label' => $l . 'results',
                    'type' => 'dropdown',
                    'options' => [
                        EmbedPayload::RESULTS_INHERIT => $l . 'results_inherit',
                        EmbedPayload::RESULTS_NEVER => $l . 'results_never',
                    ],
                    'default' => EmbedPayload::RESULTS_INHERIT,
                    'commentAbove' => $l . 'results_comment',
                ],
            ],
            'sourcePath' => plugins_path('renick/survey/content/blocks/' . self::HANDLE),
        ]];
    }

    public function unavailableNotice(string $handle): string
    {
        return $this->isAvailable()
            ? parent::unavailableNotice($handle)
            : trans('renick.survey::lang.content_block.unavailable');
    }

    public function flushCaches(): void
    {
        Survey::query()->pluck('id')->each(fn ($id) => Survey::flushChoicesCache((int) $id));
    }
}
