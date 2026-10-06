<?php

namespace Ernestdefoe\Sonic\Search\Post;

use Ernestdefoe\Sonic\Search\SonicFulltextFilter;
use Flarum\Post\Filter\FulltextFilter as DatabaseFulltextFilter;

class FulltextFilter extends SonicFulltextFilter
{
    protected function index(): string
    {
        return 'posts';
    }

    protected function fallback(): string
    {
        return DatabaseFulltextFilter::class;
    }
}
