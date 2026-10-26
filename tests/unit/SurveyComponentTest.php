<?php namespace Renick\Survey\Tests\Unit;

use AjaxException;
use Renick\Survey\Components\Survey as SurveyComponent;
use Renick\Survey\Models\Survey;
use Renick\Survey\Models\SurveyChoice;
use Renick\Survey\Models\SurveyEvent;

class SurveyComponentTest extends SurveyTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->setEnv('SURVEY_IP_ADDRESS_LIMIT', '1');
        $this->setEnv('SURVEY_COOKIE_LIMIT', 'true');
        $this->setEnv('SURVEY_UNIQUE_EMAIL', 'true');
        $this->setEnv('SURVEY_DISABLE_EMAIL_NOTIFICATIONS', 'true');
        request()->server->set('REMOTE_ADDR', '10.0.0.1');
    }

    protected function makeComponent(Survey $survey): SurveyComponent
    {
        $component = new SurveyComponent(null, ['surveyId' => $survey->id]);
        $component->getSurvey(); // as on page render: starts the submit timer
        return $component;
    }

    private function submit(SurveyComponent $component, Survey $survey, array $choices, array $extra = []): array
    {
        static $n = 0;
        $identity = $survey->is_anonym ? [] : ['user_name' => 'Tester', 'user_email' => 'tester' . (++$n) . '@example.com'];
        request()->replace(array_merge(['survey' => [$survey->id => $choices]], $identity, $extra));
        return $component->onSubmitSurvey();
    }

    public function testDetailsAndProperties(): void
    {
        $component = new SurveyComponent();
        $details = $component->componentDetails();
        $props = $component->defineProperties();

        $this->assertNotSame($details['name'], trans($details['name']));
        $this->assertSame('dropdown', $props['surveyId']['type']);
    }

    public function testSurveyIdOptionsListsSurveys(): void
    {
        $a = $this->makeSurvey(['title' => 'A']);

        $this->assertSame([$a->id => 'A'], (new SurveyComponent())->getSurveyIdOptions());
    }

    public function testGetSurveyReturnsNullForUnknownId(): void
    {
        $this->assertNull((new SurveyComponent(null, ['surveyId' => 999]))->getSurvey());
    }

    public function testGetSurveyStartsTimer(): void
    {
        $survey = $this->makeSurvey();
        $this->makeComponent($survey);

        $this->assertTrue(\Session::has("survey_submit_start_{$survey->id}"));
    }

    public function testSubmitStoresEventAndChoices(): void
    {
        $survey = $this->makeSurvey();
        $result = $this->submit($this->makeComponent($survey), $survey, ['Red'], [
            'user_name' => 'Ann',
            'user_email' => 'ann@example.com',
            'user_comment' => '<b>Hello</b>',
        ]);

        $this->assertTrue($result['isSuccess']);
        $this->assertNull($result['error']);
        $event = SurveyEvent::first();
        $this->assertSame('Ann', $event->user_name);
        $this->assertSame('ann@example.com', $event->user_email);
        $this->assertSame('10.0.0.1', $event->ip_address);
        $this->assertSame('Hello', $event->user_meta['user_comment']);
        $this->assertSame(['Red'], SurveyChoice::pluck('option_title')->all());
        $this->assertSame('Red', $result['choices'][0]['option_title']);
        $this->assertTrue(\Session::has("renick_survey_{$survey->id}"));
    }

    public function testInvalidEmailIsNotStored(): void
    {
        $survey = $this->makeSurvey(['is_anonym' => true]);
        $this->submit($this->makeComponent($survey), $survey, ['Red'], ['user_email' => 'not-an-email']);

        $this->assertNull(SurveyEvent::first()->user_email);
    }

    public function testUnknownChoicesAreIgnored(): void
    {
        $survey = $this->makeSurvey();
        $this->submit($this->makeComponent($survey), $survey, ['Red', 'Hacked']);

        $this->assertSame(['Red'], SurveyChoice::pluck('option_title')->all());
    }

    public function testMultipleChoicesAreStored(): void
    {
        $survey = $this->makeSurvey(['is_multiselect' => true]);
        $this->submit($this->makeComponent($survey), $survey, ['Red', 'Blue']);

        $this->assertSame(2, SurveyChoice::count());
        $this->assertSame(1, SurveyEvent::count());
    }

    public function testSecondSubmissionIsRejected(): void
    {
        $survey = $this->makeSurvey();
        $component = $this->makeComponent($survey);
        $this->submit($component, $survey, ['Red']);

        $result = $this->submit($component, $survey, ['Blue']);

        $this->assertFalse($result['isSuccess']);
        $this->assertNotEmpty($result['error']);
        $this->assertSame(1, SurveyEvent::count());
    }

    public function testRepeatedVotingPossibleWhenLimitsDisabled(): void
    {
        $this->setEnv('SURVEY_COOKIE_LIMIT', 'false');
        $this->setEnv('SURVEY_IP_ADDRESS_LIMIT', '0'); // needs the fix of issue #3
        $survey = $this->makeSurvey(['is_anonym' => true]);
        $component = $this->makeComponent($survey);

        $this->submit($component, $survey, ['Red']);
        $result = $this->submit($component, $survey, ['Blue']);

        $this->assertTrue($result['isSuccess']);
        $this->assertSame(2, SurveyEvent::count());
    }

    public function testDuplicateEmailIsRejected(): void
    {
        $this->setEnv('SURVEY_COOKIE_LIMIT', 'false');
        $this->setEnv('SURVEY_IP_ADDRESS_LIMIT', '5');
        $survey = $this->makeSurvey();
        $component = $this->makeComponent($survey);
        $this->submit($component, $survey, ['Red'], ['user_email' => 'a@example.com']);

        $result = $this->submit($component, $survey, ['Blue'], ['user_email' => 'a@example.com']);

        $this->assertFalse($result['isSuccess']);
        $this->assertSame(1, SurveyEvent::count());
    }

    public function testDuplicateEmailAllowedWhenUniqueCheckDisabled(): void
    {
        $this->setEnv('SURVEY_UNIQUE_EMAIL', 'false');
        $this->setEnv('SURVEY_COOKIE_LIMIT', 'false');
        $this->setEnv('SURVEY_IP_ADDRESS_LIMIT', '5');
        $survey = $this->makeSurvey();
        $component = $this->makeComponent($survey);
        $this->submit($component, $survey, ['Red'], ['user_email' => 'a@example.com']);

        $result = $this->submit($component, $survey, ['Blue'], ['user_email' => 'a@example.com']);

        $this->assertTrue($result['isSuccess']);
        $this->assertSame(2, SurveyEvent::count());
    }

    public function testEmptyRequestThrowsAjaxException(): void
    {
        $survey = $this->makeSurvey();
        request()->replace([]);

        $this->expectException(AjaxException::class);
        $this->makeComponent($survey)->onSubmitSurvey();
    }

    public function testUnknownSurveyThrowsAjaxException(): void
    {
        $survey = $this->makeSurvey();
        request()->replace(['survey' => [9999 => ['Red']]]);

        $this->expectException(AjaxException::class);
        $this->makeComponent($survey)->onSubmitSurvey();
        $this->assertSame(0, SurveyEvent::count());
    }

    public function testSurveyIdMismatchThrowsAndStoresNothing(): void
    {
        $bound = $this->makeSurvey(['title' => 'Bound']);
        $other = $this->makeSurvey(['title' => 'Other']);
        request()->replace(['survey' => [$other->id => ['Red']]]);

        try {
            $this->makeComponent($bound)->onSubmitSurvey();
            $this->fail('Expected AjaxException');
        } catch (AjaxException $e) {
            $this->assertSame(0, SurveyEvent::count());
        }
    }

    public function testSubmitDurationIsRecorded(): void
    {
        $survey = $this->makeSurvey();
        $this->submit($this->makeComponent($survey), $survey, ['Red']);

        $this->assertNotNull(SurveyEvent::first()->submit_duration);
        $this->assertFalse(\Session::has("survey_submit_start_{$survey->id}"));
    }

    public function testInactiveSurveyIsRejected(): void
    {
        $survey = $this->makeSurvey(['is_active' => false]);

        try {
            $this->submit($this->makeComponent($survey), $survey, ['Red']);
            $this->fail('Expected AjaxException');
        } catch (AjaxException $e) {
            $this->assertSame(0, SurveyEvent::count());
        }
    }

    public function testNoValidChoiceIsRejected(): void
    {
        $survey = $this->makeSurvey();

        try {
            $this->submit($this->makeComponent($survey), $survey, ['Hacked']);
            $this->fail('Expected AjaxException');
        } catch (AjaxException $e) {
            $this->assertSame(0, SurveyEvent::count());
        }
    }

    public function testSingleSelectSurveyRejectsMultipleChoices(): void
    {
        $survey = $this->makeSurvey(['is_multiselect' => false]);

        try {
            $this->submit($this->makeComponent($survey), $survey, ['Red', 'Blue']);
            $this->fail('Expected AjaxException');
        } catch (AjaxException $e) {
            $this->assertSame(0, SurveyEvent::count());
        }
    }

    public function testNonAnonymousSurveyRequiresNameAndEmail(): void
    {
        $survey = $this->makeSurvey(['is_anonym' => false]);

        try {
            $this->submit($this->makeComponent($survey), $survey, ['Red'], ['user_name' => '']);
            $this->fail('Expected AjaxException');
        } catch (AjaxException $e) {
            $this->assertSame(0, SurveyEvent::count());
        }
    }
}
