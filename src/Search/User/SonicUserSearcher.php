<?php

namespace Ernestdefoe\Sonic\Search\User;

use Flarum\User\Search\UserSearcher;

/**
 * Its own class so the Sonic driver owns its fulltext filter without touching
 * the database driver's. Visibility, filters, sort and pagination are core's.
 */
class SonicUserSearcher extends UserSearcher
{
}
