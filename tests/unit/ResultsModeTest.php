<?php namespace Renick\Survey\Tests\Unit;

use Renick\Survey\Classes\SubmissionResult;
use Renick\Survey\Classes\SubmissionService;
use Renick\Survey\Components\Survey as SurveyComponent;
use Renick\Survey\Models\Survey;

class ResultsModeTest extends SurveyTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->setEnv('SURVEY_IP_ADDRESS_LIMIT', '0');
        $this->setEnv('SURVEY_COOKIE_LIMIT', 'false');
        $this->setEnv('SURVEY_DISABLE_EMAIL_NOTIFICATIONS', 'true');
        request()->server->set('REMOTE_ADDR', '10.0.0.1');
    }

    public function testDefaultIsAfterVote(): void
    {
        $survey = $this->makeSurvey();
        $fresh = Survey::find($survey->id);

        $this->assertSame('after_vote', $fresh->results_mode);
        $this->assertFalse($fresh->resultsVisible(false));
        $this->assertTrue($fresh->resultsVisible(true));
    }

    public function testNeverAndAlways(): void
    {
        $never = $this->makeSurvey(['results_mode' => 'never']);
        $always = $this->makeSurvey(['results_mode' => 'always']);

        $this->assertFalse($never->resultsVisible(true));
        $this->assertTrue($always->resultsVisible(false));
    }

    public function testUnknownModeFallsBackToDefault(): void
    {
        $survey = $this->makeSurvey();
        $survey->results_mode = 'bogus';

        $this->assertSame('after_vote', $survey->getEffectiveResultsMode());
    }

    public function testRulesRejectUnknownMode(): void
    {
        $survey = new Survey();
        $survey->title = 'X';
        $survey->results_mode = 'bogus';

        $this->expectException(\October\Rain\Database\ModelException::class);
        $survey->validate();
    }

    public function testModeOptionsAreTranslated(): void
    {
        $options = (new Survey())->getResultsModeOptions();

        $this->assertSame(['after_vote', 'never', 'always'], array_keys($options));
        foreach ($options as $label) {
            $this->assertStringNotContainsString('renick.survey::', $label);
        }
    }

    public function testComponentHidesTotalsInNeverMode(): void
    {
        $survey = $this->makeSurvey(['is_anonym' => true, 'results_mode' => 'never']);
        $component = new SurveyComponent(null, ['surveyId' => $survey->id]);
        request()->replace(['survey' => [$survey->id => ['Red']]]);

        $response = $component->onSubmitSurvey();

        $this->assertTrue($response['isSuccess']);
        $this->assertSame([], $response['choices']);
        $this->assertSame(1, $survey->events()->count());
    }

    public function testComponentReturnsTotalsInAfterVoteMode(): void
    {
        $survey = $this->makeSurvey(['is_anonym' => true]);
        $component = new SurveyComponent(null, ['surveyId' => $survey->id]);
        request()->replace(['survey' => [$survey->id => ['Red']]]);

        $response = $component->onSubmitSurvey();

        $this->assertSame('Red', $response['choices'][0]['option_title']);
    }

    public function testServiceResults(): void
    {
        $service = new SubmissionService();
        $survey = $this->makeSurvey(['is_anonym' => true]);

        $this->assertSame(SubmissionResult::INVALID, $service->submit($survey, ['Nope'])->status);
        $this->assertSame(SubmissionResult::INVALID, $service->submit($survey, ['Red', 'Blue'])->status);

        $ok = $service->submit($survey, ['Red']);
        $this->assertTrue($ok->isOk());
        $this->assertNotNull($ok->event);

        $survey->is_active = false;
        $this->assertSame(SubmissionResult::INVALID, $service->submit($survey, ['Red'])->status);
    }

    public function testServiceThrottles(): void
    {
        $this->setEnv('SURVEY_THROTTLE_PER_MINUTE', '1');
        \Illuminate\Support\Facades\RateLimiter::clear('renick_survey_submit_1_10.0.0.1');
        $survey = $this->makeSurvey(['is_anonym' => true]);
        $service = new SubmissionService();

        $this->assertTrue($service->submit($survey, ['Red'])->isOk());
        $this->assertSame(SubmissionResult::THROTTLED, $service->submit($survey, ['Red'])->status);
    }
}
