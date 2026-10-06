<?php

namespace Ernestdefoe\Sonic\Listener;

use Ernestdefoe\Sonic\Sonic;
use Flarum\Settings\Event\Deserializing;

/**
 * The admin page receives every setting. The Sonic password is swapped for a
 * yes/no flag, so it is never sent back to a browser, even an admin's; the
 * field stays write-only.
 */
class HidePassword
{
    public function handle(Deserializing $event): void
    {
        $key = Sonic::KEY.'.password';

        $event->settings[Sonic::KEY.'.password_set'] = ($event->settings[$key] ?? '') !== '';
        unset($event->settings[$key]);
    }
}
