<?php namespace Renick\Survey\Classes\Compat;

use Config;

/**
 * Reads plugin settings with a fallback chain that works on all supported
 * October CMS versions (3.x and 4.x): plugin config file, then the legacy
 * SURVEY_* environment variable, then the built-in default.
 */
class PluginConfig
{
    /**
     * @var array<string, array{0: string, 1: mixed}> key => [env name, default]
     */
    protected const DEFAULTS = [
        'cookie_limit' => ['SURVEY_COOKIE_LIMIT', true],
        'ip_address_limit' => ['SURVEY_IP_ADDRESS_LIMIT', 1],
        'unique_email' => ['SURVEY_UNIQUE_EMAIL', true],
        'disable_email_notifications' => ['SURVEY_DISABLE_EMAIL_NOTIFICATIONS', false],
        'throttle_per_minute' => ['SURVEY_THROTTLE_PER_MINUTE', 20],
        'export_format' => ['SURVEY_EXPORT_FORMAT', 'auto'],
    ];

    public static function get(string $key): mixed
    {
        [$envName, $default] = self::DEFAULTS[$key] ?? [null, null];

        $value = Config::get('renick.survey::' . $key);
        if ($value === null && $envName !== null) {
            $value = getenv($envName) !== false ? getenv($envName) : ($_ENV[$envName] ?? null);
        }

        return $value ?? $default;
    }

    public static function bool(string $key): bool
    {
        $default = (bool) (self::DEFAULTS[$key][1] ?? false);
        return self::toBool(self::get($key), $default);
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    /**
     * Strict boolean parsing: the strings "false", "0", "off", "no" are false.
     */
    public static function toBool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed ?? $default;
    }
}
