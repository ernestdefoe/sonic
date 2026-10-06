<?php

namespace Ernestdefoe\Sonic\Listener;

use Ernestdefoe\Sonic\Sonic;
use Flarum\Settings\Event\Deserializing;
use Flarum\Settings\Event\Saving;

/**
 * The Sonic password is write-only.
 *
 * hide(): the admin page receives every setting, so the password is swapped
 * for a yes/no flag and never reaches a browser, even an admin's.
 *
 * keep(): 🚨 because the browser never has the password, the settings page
 * counts the empty field as changed and sends "" with EVERY save — which
 * silently wiped the password whenever any other Sonic setting was saved
 * (seen on dev: save the bucket, Sonic answers auth_failed). An empty value
 * is therefore never written.
 */
class WriteOnlyPassword
{
    protected const KEY = Sonic::KEY.'.password';

    public function hide(Deserializing $event): void
    {
        $event->settings[Sonic::KEY.'.password_set'] = ($event->settings[self::KEY] ?? '') !== '';
        unset($event->settings[self::KEY]);
    }

    public function keep(Saving $event): void
    {
        if (array_key_exists(self::KEY, $event->settings) && trim((string) $event->settings[self::KEY]) === '') {
            unset($event->settings[self::KEY]);
        }
    }
}
