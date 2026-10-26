<?php namespace Renick\Survey\Classes\Content;

use Renick\Survey\Models\Survey;

/**
 * Per-visitor HTML fragment of an embedded survey (form, closed state or results).
 * Plain markup without inline scripts, so it can be inserted with innerHTML.
 */
final class EmbedRenderer
{
    public function __construct(private readonly EmbedPayload $payload)
    {
    }

    /** Whether the totals may be shown: the block can only restrict what the survey allows. */
    public function resultsAllowed(Survey $survey, bool $hasVoted): bool
    {
        return $this->payload->results !== EmbedPayload::RESULTS_NEVER && $survey->resultsVisible($hasVoted);
    }

    public function canVote(Survey $survey): bool
    {
        return (bool) $survey->is_active && $survey->isAccessible();
    }

    /** @param string|null $status message shown above the fragment (e.g. thank you) */
    public function render(?Survey $survey, ?string $status = null, bool $justVoted = false): string
    {
        if (!$survey) {
            return '<p class="rc-survey__notice" role="status" tabindex="-1" data-rc-survey-focus>'
                . e(trans('renick.survey::lang.content_block.not_available')) . '</p>';
        }

        $canVote = !$justVoted && $this->canVote($survey);
        $html = $this->heading($survey);

        if ($status !== null) {
            $html .= '<p class="rc-survey__notice rc-survey__notice--success" role="status" tabindex="-1" data-rc-survey-focus>' . e($status) . '</p>';
        } elseif (!$canVote) {
            $key = $survey->is_active ? 'already_voted' : 'closed';
            $html .= '<p class="rc-survey__notice" role="status" tabindex="-1" data-rc-survey-focus>' . e(trans('renick.survey::lang.content_block.' . $key)) . '</p>';
        }

        if ($canVote) {
            $html .= $this->form($survey);
        }

        if ($this->resultsAllowed($survey, !$canVote)) {
            $html .= $this->results($survey);
        }

        return $html;
    }

    private function heading(Survey $survey): string
    {
        if ($this->payload->shellHasHeading) {
            return '';
        }

        $level = $this->payload->headingLevel;
        return "<h{$level} class=\"rc-survey__title\" id=\"" . e($this->id('title')) . '">' . e($survey->title) . "</h{$level}>";
    }

    private function form(Survey $survey): string
    {
        $type = $survey->is_multiselect ? 'checkbox' : 'radio';
        $legend = $survey->is_multiselect ? 'legend_multi' : 'legend_single';
        $action = BlockData::endpoint($survey->id);

        $html = '<form class="rc-survey__form" method="post" action="' . e($action) . '" data-rc-survey-form novalidate>';
        $html .= '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
        foreach ($this->payload->toQuery() as $name => $value) {
            $html .= '<input type="hidden" name="' . e($name) . '" value="' . e((string) $value) . '">';
        }

        $description = trim((string) $survey->description);
        if ($description !== '') {
            $html .= '<div class="rc-survey__description" id="' . e($this->id('desc')) . '">' . $survey->getDescriptionHtml() . '</div>';
        }

        $html .= '<fieldset class="rc-survey__fieldset"'
            . ($description !== '' ? ' aria-describedby="' . e($this->id('desc')) . '"' : '') . '>';
        $html .= '<legend class="rc-survey__legend">' . e(trans('renick.survey::lang.content_block.' . $legend)) . '</legend>';

        foreach (array_values((array) $survey->options) as $i => $option) {
            $title = (string) ($option['title'] ?? '');
            if ($title === '') {
                continue;
            }

            $text = trim((string) ($option['text'] ?? ''));
            $inputId = $this->id('opt-' . $i);
            $html .= '<div class="rc-survey__option">'
                . '<input type="' . $type . '" name="choices[]" id="' . e($inputId) . '" value="' . e($title) . '">'
                . '<label for="' . e($inputId) . '">' . ($text !== '' ? Survey::getOptionHtml($option) : e($title)) . '</label>'
                . '</div>';
        }
        $html .= '</fieldset>';

        if (!$survey->is_anonym) {
            $html .= $this->identityFields();
        }

        $html .= '<div class="rc-survey__error" role="alert" hidden></div>';
        $html .= '<button type="submit" class="rc-survey__submit" disabled>' . e(trans('renick.survey::lang.component.submit')) . '</button>';
        $html .= '</form>';

        return $html;
    }

    private function identityFields(): string
    {
        $c = 'renick.survey::lang.component.';
        $field = function (string $name, string $type, string $label, bool $required, string $autocomplete): string {
            $id = $this->id($name);
            return '<div class="rc-survey__field"><label for="' . e($id) . '">' . e(trans($label)) . ($required ? ' <span aria-hidden="true">*</span>' : '') . '</label>'
                . '<input type="' . $type . '" name="' . $name . '" id="' . e($id) . '" autocomplete="' . $autocomplete . '"'
                . ($required ? ' required aria-required="true"' : '') . '></div>';
        };

        $html = '<fieldset class="rc-survey__fieldset rc-survey__identity"><legend class="rc-survey__legend">' . e(trans($c . 'user_details')) . '</legend>';
        $html .= $field('user_name', 'text', $c . 'user_name', true, 'name');
        $html .= $field('user_email', 'email', $c . 'user_email', true, 'email');
        $html .= $field('user_phone', 'tel', $c . 'user_phone', false, 'tel');
        $html .= '<div class="rc-survey__field"><label for="' . e($this->id('user_comment')) . '">' . e(trans($c . 'user_comment')) . '</label>'
            . '<textarea name="user_comment" id="' . e($this->id('user_comment')) . '" maxlength="2000" rows="4"></textarea></div>';
        $html .= '<div class="rc-survey__field rc-survey__field--check"><input type="checkbox" name="user_privacy" value="1" id="' . e($this->id('privacy')) . '" required aria-required="true">'
            . '<label for="' . e($this->id('privacy')) . '">' . \Html::clean(trans($c . 'user_privacy_notice')) . '</label></div>';
        $html .= '</fieldset>';

        return $html;
    }

    private function results(Survey $survey): string
    {
        $rows = $survey->getTotalChoices();
        if ($rows === []) {
            return '';
        }

        $html = '<div class="rc-survey__results"><p class="rc-survey__results-title" id="' . e($this->id('results')) . '">'
            . e(trans('renick.survey::lang.component.total_choices')) . '</p>'
            . '<ul aria-labelledby="' . e($this->id('results')) . '">';
        foreach ($rows as $row) {
            $html .= '<li><span class="rc-survey__count">' . (int) $row['total'] . '</span> ' . e((string) $row['option_title']) . '</li>';
        }

        return $html . '</ul></div>';
    }

    private function id(string $part): string
    {
        return 'rc-survey-' . $this->payload->surveyId . '-' . $part;
    }
}
