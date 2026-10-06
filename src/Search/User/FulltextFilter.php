<?php

namespace Ernestdefoe\Sonic\Search\User;

use Ernestdefoe\Sonic\Search\SonicFulltextFilter;
use Flarum\User\Search\FulltextFilter as DatabaseFulltextFilter;

class FulltextFilter extends SonicFulltextFilter
{
    protected function index(): string
    {
        return 'users';
    }

    protected function fallback(): string
    {
        return DatabaseFulltextFilter::class;
    }

    /** Names are not prose: no language detection, no stop words. */
    protected function lang(): ?string
    {
        return 'none';
    }
}
