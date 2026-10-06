<?php

namespace Ernestdefoe\Sonic\Search;

use Flarum\Search\AbstractDriver;

/**
 * Chosen per resource through core's `search_driver_<ModelClass>` setting.
 * Core routes only text searches here; browsing and filtering stay on the
 * database driver, and the searchers' getQuery() still applies visibility —
 * Sonic only ever supplies candidate ids and their order.
 */
class SonicSearchDriver extends AbstractDriver
{
    public static function name(): string
    {
        return 'sonic';
    }
}
