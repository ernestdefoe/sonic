<?php

namespace Ernestdefoe\Sonic\Provider;

use Ernestdefoe\Sonic\Search\Discussion\SonicDiscussionSearcher;
use Ernestdefoe\Sonic\Search\Post\SonicPostSearcher;
use Ernestdefoe\Sonic\Search\User\SonicUserSearcher;
use Ernestdefoe\Sonic\Sonic;
use Flarum\Discussion\Search\DiscussionSearcher;
use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Post\Filter\PostSearcher;
use Flarum\User\Search\UserSearcher;

/**
 * Filters and mutators registered on core's searchers — by core and by
 * extensions such as flarum/tags (`tag:`) — are mirrored onto the Sonic
 * searchers, so a Sonic search honours exactly the same refinements as the
 * database one. Permissions never depend on this: getQuery() applies
 * whereVisibleTo either way.
 */
class SearchProvider extends AbstractServiceProvider
{
    protected const MIRROR = [
        SonicDiscussionSearcher::class => DiscussionSearcher::class,
        SonicUserSearcher::class => UserSearcher::class,
        SonicPostSearcher::class => PostSearcher::class,
    ];

    public function register(): void
    {
        $this->container->singleton(Sonic::class);

        $this->container->extend('flarum.search.filters', function (array $filters) {
            foreach (self::MIRROR as $mine => $parent) {
                $filters[$mine] = array_values(array_unique(array_merge($filters[$mine] ?? [], $filters[$parent] ?? [])));
            }

            return $filters;
        });

        $this->container->extend('flarum.search.mutators', function (array $mutators) {
            foreach (self::MIRROR as $mine => $parent) {
                $mutators[$mine] = array_merge($mutators[$mine] ?? [], $mutators[$parent] ?? []);
            }

            return $mutators;
        });
    }
}
