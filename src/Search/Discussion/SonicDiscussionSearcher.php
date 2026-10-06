<?php

namespace Ernestdefoe\Sonic\Search\Discussion;

use Flarum\Discussion\Search\DiscussionSearcher;

/**
 * Its own class so the Sonic driver owns its fulltext filter without touching
 * the database driver's. Visibility, filters, sort and pagination are core's.
 */
class SonicDiscussionSearcher extends DiscussionSearcher
{
}
