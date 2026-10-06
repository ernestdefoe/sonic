<?php

namespace Ernestdefoe\Sonic\Job;

use Ernestdefoe\Sonic\Search\Discussion\DiscussionIndexer;
use Ernestdefoe\Sonic\Search\Post\PostIndexer;
use Ernestdefoe\Sonic\Search\User\UserIndexer;
use Ernestdefoe\Sonic\Sonic;
use Flarum\Queue\AbstractJob;
use Illuminate\Contracts\Container\Container;

/**
 * Rebuilds every index off the request when a queue worker runs; on the
 * default sync queue it runs inline, which is fine for small forums.
 */
class RebuildJob extends AbstractJob
{
    public const INDEXERS = [DiscussionIndexer::class, PostIndexer::class, UserIndexer::class];

    public int $tries = 1;
    public int $timeout = 3600;

    public function handle(Container $container): void
    {
        foreach (self::INDEXERS as $indexer) {
            $container->make($indexer)->build();
        }

        $container->make(Sonic::class)->consolidate();
    }
}
