<?php namespace Renick\Survey\Tests\Unit;

use Renick\Survey\Models\Survey;

/**
 * Rich text of descriptions and options is printed unescaped by the templates, so it is cleaned first.
 */
class SanitizingTest extends SurveyTestCase
{
    public function testDescriptionIsCleaned(): void
    {
        $survey = $this->makeSurvey(['description' => '<p onclick="x()">Hi</p><script>alert(1)</script><img src=x onerror=alert(1)>']);

        $html = $survey->getDescriptionHtml();

        $this->assertStringContainsString('Hi', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    public function testOptionTextIsCleaned(): void
    {
        $html = Survey::getOptionHtml(['title' => 'A', 'text' => '<b>Bold</b><script>alert(1)</script>']);

        $this->assertStringContainsString('<b>Bold</b>', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function testMissingTextGivesEmptyString(): void
    {
        $this->assertSame('', Survey::getOptionHtml(['title' => 'A']));
        $this->assertSame('', (new Survey())->getDescriptionHtml());
    }

    public function testStatisticsPartialEscapesOptionTitles(): void
    {
        $survey = $this->makeSurvey(['options' => [['title' => '<img src=x onerror=alert(1)>', 'text' => 'x']]]);
        $this->makeEvent($survey, [], ['<img src=x onerror=alert(1)>']);

        $partial = file_get_contents(__DIR__ . '/../../partials/_survey_statistics.php');

        $this->assertStringContainsString("e(\$choice['option_title'])", $partial);
    }
}
