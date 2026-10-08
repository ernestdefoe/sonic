<?php

namespace Ernestdefoe\Sonic\Search\Discussion;

use Ernestdefoe\Sonic\Search\AbstractIndexer;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Illuminate\Database\Eloquent\Builder;

/**
 * One object per visible discussion: its title, then the text of its visible
 * comments in order, up to MAX_TEXT.
 *
 * @extends AbstractIndexer<Discussion>
 */
class DiscussionIndexer extends AbstractIndexer
{
    protected const FIELDS = ['title', 'hidden_at'];

    public static function index(): string
    {
        return 'discussions';
    }

    protected function baseQuery(): Builder
    {
        return Discussion::query()->whereNull('hidden_at')->select('id', 'title', 'hidden_at');
    }

    protected function textFor(array $models): array
    {
        $texts = [];
        foreach ($models as $d) {
            if ($d->hidden_at === null) {
                $texts[(int) $d->id] = (string) $d->title;
            }
        }

        if (! $texts) {
            return [];
        }

        $wanted = count($texts);
        $full = [];

        Post::query()
            ->where('type', 'comment')
            ->whereNull('hidden_at')
            ->whereIn('discussion_id', array_keys($texts))
            ->select('id', 'discussion_id', 'content')
            ->orderBy('discussion_id')
            ->orderBy('number')
            // 🚨 Stops reading once every discussion is full: a 5,000-post
            // thread must not be read whole for an object capped at MAX_TEXT.
            ->chunk(static::CHUNK, function ($posts) use (&$texts, &$full, $wanted) {
                foreach ($posts as $post) {
                    $id = (int) $post->discussion_id;
                    if (isset($full[$id])) {
                        continue;
                    }
                    $texts[$id] .= ' '.$post->getAttributes()['content'];
                    if (strlen($texts[$id]) >= static::MAX_TEXT * 2) {
                        $full[$id] = true;
                    }
                }

                return count($full) < $wanted;
            });

        return $texts;
    }
}
