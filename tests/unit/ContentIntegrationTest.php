<?php namespace Renick\Survey\Tests\Unit;

use Renick\Survey\Classes\Content\BlockData;
use Renick\Survey\Classes\Content\EmbedPayload;
use Renick\Survey\Classes\Content\EmbedRenderer;

/**
 * The survey block of Renick.Content. Integration-specific tests are skipped where Content is not installed.
 */
class ContentIntegrationTest extends SurveyTestCase
{
    private function requireContent(): void
    {
        if (!class_exists(\Renick\Content\Classes\Integrations\Integration::class)) {
            $this->markTestSkipped('Renick.Content is not installed');
        }
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->setEnv('SURVEY_IP_ADDRESS_LIMIT', '0');
        $this->setEnv('SURVEY_COOKIE_LIMIT', 'false');
        request()->server->set('REMOTE_ADDR', '10.0.0.1');
    }

    public function testPayloadSignatureBindsAllSettings(): void
    {
        $payload = new EmbedPayload(5, 'inherit', 3, false);
        $sig = $payload->sign();

        $this->assertTrue($payload->verify($sig));
        $this->assertFalse($payload->verify(''));
        $this->assertFalse((new EmbedPayload(6, 'inherit', 3, false))->verify($sig));
        $this->assertFalse((new EmbedPayload(5, 'never', 3, false))->verify($sig));
        $this->assertFalse((new EmbedPayload(5, 'inherit', 4, false))->verify($sig));
        $this->assertFalse((new EmbedPayload(5, 'inherit', 3, true))->verify($sig));
    }

    public function testPayloadFromBlockDataNormalizesInput(): void
    {
        $this->assertNull(EmbedPayload::fromBlockData([]));
        $this->assertNull(EmbedPayload::fromBlockData(['survey_id' => 'abc']));

        $payload = EmbedPayload::fromBlockData(['survey_id' => '7', 'heading_level' => '9', 'results' => 'weird', 'title' => ' Hi ']);

        $this->assertSame("7", $payload->ref);
        $this->assertSame(6, $payload->headingLevel);
        $this->assertSame('inherit', $payload->results);
        $this->assertTrue($payload->shellHasHeading);
        $this->assertSame(2, EmbedPayload::clampLevel(1));
        $this->assertSame(3, EmbedPayload::clampLevel('x'));
    }

    public function testPayloadRoundTripThroughRequest(): void
    {
        $payload = new EmbedPayload(5, 'never', 4, true);
        $restored = EmbedPayload::fromRequest(5, $payload->toQuery());

        $this->assertTrue($restored->verify($payload->toQuery()['sig']));
    }

    public function testResultsPolicyCanOnlyBeRestricted(): void
    {
        $inherit = new EmbedRenderer(new EmbedPayload(1, 'inherit'));
        $never = new EmbedRenderer(new EmbedPayload(1, 'never'));
        $always = $this->makeSurvey(['results_mode' => 'always']);
        $afterVote = $this->makeSurvey();
        $hidden = $this->makeSurvey(['results_mode' => 'never']);

        $this->assertTrue($inherit->resultsAllowed($always, false));
        $this->assertFalse($never->resultsAllowed($always, true));
        $this->assertFalse($inherit->resultsAllowed($afterVote, false));
        $this->assertTrue($inherit->resultsAllowed($afterVote, true));
        $this->assertFalse($inherit->resultsAllowed($hidden, true));
    }

    public function testFragmentHasFormStructureAndEscapesContent(): void
    {
        $survey = $this->makeSurvey([
            'title' => '<b>Bold</b>',
            'options' => [['title' => 'A"><script>', 'text' => '<p>One</p><script>alert(1)</script>']],
        ]);

        $html = (new EmbedRenderer(new EmbedPayload($survey->id)))->render($survey);

        $this->assertStringContainsString('<fieldset', $html);
        $this->assertStringContainsString('<legend', $html);
        $this->assertStringContainsString('&lt;b&gt;Bold&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('<h3', $html);
    }

    public function testFragmentSkipsHeadingWhenShellHasOne(): void
    {
        $survey = $this->makeSurvey();

        $html = (new EmbedRenderer(new EmbedPayload($survey->id, 'inherit', 3, true)))->render($survey);

        $this->assertStringNotContainsString('<h3', $html);
    }

    public function testFragmentForClosedAndMissingSurvey(): void
    {
        $closed = $this->makeSurvey(['is_active' => false]);
        $renderer = new EmbedRenderer(new EmbedPayload($closed->id));

        $this->assertStringNotContainsString('<form', $renderer->render($closed));
        $this->assertStringContainsString('role="status"', $renderer->render($closed));
        $this->assertStringContainsString('role="status"', $renderer->render(null));
    }

    public function testFragmentIdentityFieldsOnlyForNamedSurveys(): void
    {
        $anonymous = $this->makeSurvey(['is_anonym' => true]);
        $named = $this->makeSurvey(['is_anonym' => false]);

        $this->assertStringNotContainsString('user_email', (new EmbedRenderer(new EmbedPayload($anonymous->id)))->render($anonymous));
        $this->assertStringContainsString('user_email', (new EmbedRenderer(new EmbedPayload($named->id)))->render($named));
    }

    public function testMultiselectUsesCheckboxes(): void
    {
        $multi = $this->makeSurvey(['is_multiselect' => true]);

        $this->assertStringContainsString('type="checkbox" name="choices[]"', (new EmbedRenderer(new EmbedPayload($multi->id)))->render($multi));
    }

    public function testBlockDataEnrichment(): void
    {
        $survey = $this->makeSurvey();
        $block = ['type' => 'survey', 'data' => ['survey_id' => (string) $survey->id, 'title' => 'T']];

        BlockData::enrich($block, false);

        $info = $block['data']['_survey'];
        $this->assertTrue($info['valid']);
        $this->assertSame('T', $info['heading']);
        $this->assertStringContainsString('sig=', $info['query']);
        $this->assertArrayNotHasKey('preview', $info);

        BlockData::enrich($block, true);
        $this->assertSame($survey->title, $block['data']['_survey']['preview']['title']);
    }

    public function testBlockDataForUnknownSurveyIsInvalid(): void
    {
        $block = ['type' => 'survey', 'data' => ['survey_id' => '99999']];

        BlockData::enrich($block, false);

        $this->assertFalse($block['data']['_survey']['valid']);
    }

    public function testIntegrationDefinitionAndSource(): void
    {
        $this->requireContent();
        $integration = new \Renick\Survey\Classes\Content\SurveyContentIntegration();

        $this->assertSame('survey', $integration->code());
        $this->assertSame('Renick.Survey', $integration->pluginCode());
        $this->assertSame(['survey'], $integration->blockHandles());
        $this->assertFalse($integration->variesPerVisitor());
        $this->assertSame([], $integration->settingsFields());

        $definition = $integration->blockDefinitions()[0];
        $this->assertSame('survey', $definition['handle']);
        $this->assertSame('recordpicker', $definition['fields']['survey_id']['type']);
        $this->assertDirectoryExists($definition['sourcePath']);
        $this->assertFileExists($definition['sourcePath'] . '/survey.htm');

        $source = $integration->recordSources()['survey-surveys'];
        $a = $this->makeSurvey(['title' => 'Alpha poll']);
        $this->makeSurvey(['title' => 'Beta poll']);
        $this->assertSame([$a->uuid], array_column($source->search('alpha', 10), 'id'));
        $this->assertCount(2, $source->search('', 10));
        $this->assertSame([$a->uuid], array_column($source->labels([$a->uuid, strtoupper($a->uuid), '99999', 'x']), 'id'));
        // blocks of older versions stored the numeric id, the picker still shows their survey
        $this->assertSame([(string) $a->id], array_column($source->labels([(string) $a->id]), 'id'));
    }

    public function testRegistrationEventReturnsIntegration(): void
    {
        $this->requireContent();

        $results = \Event::fire('renick.content.registerIntegrations');
        $classes = array_map(fn ($r) => array_map('get_class', (array) $r), array_filter($results));

        $this->assertContains(\Renick\Survey\Classes\Content\SurveyContentIntegration::class, array_merge(...array_values($classes)));
    }
}
