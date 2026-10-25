<?php namespace Renick\Survey\Tests\Unit;

use Renick\Survey\Components\Survey as SurveyComponent;
use Renick\Survey\Plugin;
use System\Classes\PluginManager;

class PluginTest extends SurveyTestCase
{
    public function testPluginIsRegistered(): void
    {
        $plugin = PluginManager::instance()->findByIdentifier('Renick.Survey');
        $this->assertInstanceOf(Plugin::class, $plugin);
    }

    public function testComponentIsRegistered(): void
    {
        $components = (new Plugin(app()))->registerComponents();
        $this->assertSame('survey', $components[SurveyComponent::class]);
    }

    public function testMailTemplateIsRegistered(): void
    {
        $this->assertContains('renick.survey::mail.admin_notification', (new Plugin(app()))->registerMailTemplates());
    }

    public function testNavigationAndPermissions(): void
    {
        $config = \Symfony\Component\Yaml\Yaml::parseFile(dirname(__DIR__, 2) . '/plugin.yaml');

        $this->assertSame('renick/survey/surveycontroller', $config['navigation']['renick-survey']['url']);
        $this->assertContains('renick_survey_manage', $config['navigation']['renick-survey']['permissions']);
        $this->assertArrayHasKey('renick_survey_manage', $config['permissions']);

        $this->assertNotSame('renick.survey::lang.plugin.name', trans('renick.survey::lang.plugin.name'));
        $this->assertNotSame(
            $config['permissions']['renick_survey_manage']['label'],
            trans($config['permissions']['renick_survey_manage']['label'])
        );
    }

    public function testLanguageFilesHaveSameKeys(): void
    {
        $dir = dirname(__DIR__, 2) . '/lang';
        $flatten = function (array $a, string $p = '') use (&$flatten): array {
            $out = [];
            foreach ($a as $k => $v) {
                is_array($v) ? $out += $flatten($v, "$p$k.") : $out["$p$k"] = true;
            }
            return $out;
        };
        $en = array_keys($flatten(require "$dir/en/lang.php"));
        foreach (glob("$dir/*/lang.php") as $file) {
            $keys = array_keys($flatten(require $file));
            $this->assertSame([], array_diff($en, $keys), "Missing keys in $file");
        }
    }
}
