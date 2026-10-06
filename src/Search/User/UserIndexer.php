<?php

namespace Ernestdefoe\Sonic\Search\User;

use Ernestdefoe\Sonic\Search\AbstractIndexer;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * One object per user: username and display name. Indexed with LANG(none) so
 * a name is never mistaken for a stop word. Visibility is applied at query
 * time by the searcher.
 */
class UserIndexer extends AbstractIndexer
{
    protected const FIELDS = ['username', 'nickname'];

    protected const LANG = 'none';

    public static function index(): string
    {
        return 'users';
    }

    protected function baseQuery(): Builder
    {
        return User::query();
    }

    protected function textFor(array $models): array
    {
        $texts = [];
        foreach ($models as $user) {
            $name = (string) $user->display_name;
            $texts[(int) $user->id] = $user->username.($name !== $user->username ? ' '.$name : '');
        }

        return $texts;
    }
}
