<?php namespace Renick\Survey\Tests\Unit;

use PluginTestCase;
use Renick\Survey\Models\Survey;
use Renick\Survey\Models\SurveyChoice;
use Renick\Survey\Models\SurveyEvent;

/**
 * Base class of all plugin tests.
 */
abstract class SurveyTestCase extends PluginTestCase
{
    /** The plugin dir may be a symlink, so October cannot guess the code from the path. */
    protected function guessPluginCodeFromTest()
    {
        return 'Renick.Survey';
    }

    public function setUp(): void
    {
        parent::setUp();
        \Cache::flush();
        \Session::flush();
    }

    /** Sets an env variable for the current test (restored in tearDown). */
    protected function setEnv(string $name, $value): void
    {
        $this->envBackup[$name] = $this->envBackup[$name] ?? [getenv($name), $_ENV[$name] ?? null, $_SERVER[$name] ?? null];
        putenv("$name=$value");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;

        // Newer code reads renick.survey::<key> (config file defaults are evaluated once at boot), keep it in sync.
        if (str_starts_with($name, 'SURVEY_')) {
            \Config::set('renick.survey::' . strtolower(substr($name, 7)), $value);
        }
    }

    private array $envBackup = [];

    public function tearDown(): void
    {
        foreach ($this->envBackup as $name => [$getenv, $env, $server]) {
            $getenv === false ? putenv($name) : putenv("$name=$getenv");
            $env === null ? \Illuminate\Support\Arr::forget($_ENV, $name) : $_ENV[$name] = $env;
            $server === null ? \Illuminate\Support\Arr::forget($_SERVER, $name) : $_SERVER[$name] = $server;
        }
        $this->envBackup = [];
        parent::tearDown();
    }

    protected function makeSurvey(array $attrs = []): Survey
    {
        $survey = new Survey();
        $survey->forceFill(array_merge([
            'title' => 'Favourite colour',
            'is_active' => true,
            'is_anonym' => false,
            'is_multiselect' => false,
            'options' => [
                ['title' => 'Red', 'text' => 'Red'],
                ['title' => 'Blue', 'text' => 'Blue'],
                ['title' => 'Green', 'text' => 'Green'],
            ],
        ], $attrs));
        $survey->save();
        return $survey;
    }

    protected function makeEvent(Survey $survey, array $attrs = [], array $choices = []): SurveyEvent
    {
        $event = new SurveyEvent();
        $event->forceFill(array_merge(['survey_id' => $survey->id, 'ip_address' => '10.0.0.1'], $attrs));
        $event->save();
        foreach ($choices as $title) {
            $choice = new SurveyChoice();
            $choice->forceFill([
                'survey_id' => $survey->id,
                'survey_event_id' => $event->id,
                'option_title' => $title,
            ]);
            $choice->save();
        }
        return $event;
    }
}
