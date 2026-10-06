<?php

namespace Ernestdefoe\Sonic\Search\Discussion;

use Ernestdefoe\Sonic\Search\SonicFulltextFilter;
use Flarum\Discussion\Search\FulltextFilter as DatabaseFulltextFilter;
use Illuminate\Database\Eloquent\Builder;

class FulltextFilter extends SonicFulltextFilter
{
    protected function index(): string
    {
        return 'discussions';
    }

    protected function fallback(): string
    {
        return DatabaseFulltextFilter::class;
    }

    /**
     * Search results show a "most relevant post"; like core does for a title
     * match, point it at the first post rather than leave it empty.
     */
    protected function matched(Builder $query): void
    {
        $query->addSelect('discussions.first_post_id as most_relevant_post_id');
    }
}
