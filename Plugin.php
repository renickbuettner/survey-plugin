<?php namespace Renick\Survey;

use Event;
use System\Classes\PluginBase;

/**
 * Plugin class
 */
class Plugin extends PluginBase
{
    /**
     * register method, called when the plugin is first registered.
     */
    public function register()
    {
        // Optional bridge: Content collects integrations in its own boot(), so this has to happen here.
        // The class is only referenced inside the closure, so it never autoloads without Renick.Content.
        Event::listen('renick.content.registerIntegrations', function () {
            if (!class_exists(\Renick\Content\Classes\Integrations\Integration::class)) {
                return [];
            }

            return [new \Renick\Survey\Classes\Content\SurveyContentIntegration()];
        });
    }

    /**
     * boot method, called right before the request route.
     */
    public function boot()
    {
    }

    /**
     * registerComponents used by the frontend.
     */
    public function registerComponents()
    {
        return [
            'Renick\Survey\Components\Survey'       => 'survey',
        ];
    }

    /**
     * registerSettings used by the backend.
     */
    public function registerSettings()
    {
    }

    public function registerMailTemplates()
    {
        return [
            'renick.survey::mail.admin_notification'
        ];
    }
}
