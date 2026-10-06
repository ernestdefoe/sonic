<?php

namespace Ernestdefoe\Sonic\Search\Post;

use Flarum\Post\Filter\PostSearcher;

/**
 * Its own class so the Sonic driver owns its fulltext filter without touching
 * the database driver's. Visibility, filters, sort and pagination are core's.
 */
class SonicPostSearcher extends PostSearcher
{
}
