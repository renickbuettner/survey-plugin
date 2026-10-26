<?php namespace Renick\Survey\Tests\Unit;

/**
 * Survey::isAccessible() and the env based limits.
 */
class AccessTest extends SurveyTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->setEnv('SURVEY_IP_ADDRESS_LIMIT', '1');
        $this->setEnv('SURVEY_COOKIE_LIMIT', 'true');
        request()->server->set('REMOTE_ADDR', '10.0.0.1');
    }

    public function testAccessibleWithoutPriorSubmission(): void
    {
        $this->assertTrue($this->makeSurvey()->isAccessible());
    }

    public function testIpLimitOfOneBlocksSecondSubmission(): void
    {
        $survey = $this->makeSurvey();
        $this->makeEvent($survey, ['ip_address' => '10.0.0.1']);

        $this->assertFalse($survey->isAccessible());
    }

    public function testOtherIpIsNotBlocked(): void
    {
        $survey = $this->makeSurvey();
        $this->makeEvent($survey, ['ip_address' => '10.9.9.9']);

        $this->assertTrue($survey->isAccessible());
    }

    public function testHigherIpLimitAllowsMoreSubmissions(): void
    {
        $this->setEnv('SURVEY_IP_ADDRESS_LIMIT', '3');
        $survey = $this->makeSurvey();
        $this->makeEvent($survey);
        $this->makeEvent($survey);
        $this->assertTrue($survey->isAccessible());
        $this->makeEvent($survey);
        $this->assertFalse($survey->isAccessible());
    }

    /**
     * Regression for GitHub issue #3: SURVEY_IP_ADDRESS_LIMIT=0 must disable the IP limit
     * (it used to block every vote, because count >= 0 is always true).
     */
    public function testIpLimitZeroDisablesTheLimit(): void
    {
        $this->setEnv('SURVEY_IP_ADDRESS_LIMIT', '0');
        $survey = $this->makeSurvey();

        $this->assertTrue($survey->isAccessible());

        $this->makeEvent($survey);
        $this->makeEvent($survey);
        $this->assertTrue($survey->isAccessible());
    }

    public function testCookieLimitBlocksWhenSessionMarkerSet(): void
    {
        $survey = $this->makeSurvey();
        \Session::put("renick_survey_{$survey->id}", true);

        $this->assertFalse($survey->isAccessible());
    }

    public function testCookieLimitCanBeDisabled(): void
    {
        $this->setEnv('SURVEY_COOKIE_LIMIT', 'false');
        $this->setEnv('SURVEY_IP_ADDRESS_LIMIT', '0');
        $survey = $this->makeSurvey();
        \Session::put("renick_survey_{$survey->id}", true);

        $this->assertTrue($survey->isAccessible());
    }
}
