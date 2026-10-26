<?php namespace Renick\Survey\Classes\Compat;

use Log;
use Mail;
use Throwable;

/**
 * Mail adapter: queues the message when the facade supports it, otherwise
 * sends it immediately. Never throws, a failing notification must not break
 * a vote.
 */
class Notifier
{
    public static function send(string $template, array $params, string $to, string $subject): bool
    {
        $callback = static function ($message) use ($to, $subject) {
            $message->to($to);
            $message->subject($subject);
        };

        try {
            if (method_exists(Mail::getFacadeRoot(), 'queue')) {
                Mail::queue($template, $params, $callback);
            } else {
                Mail::send($template, $params, $callback);
            }
            return true;
        } catch (Throwable $e) {
            Log::warning('Renick.Survey: notification failed: ' . $e->getMessage());
            return false;
        }
    }
}
