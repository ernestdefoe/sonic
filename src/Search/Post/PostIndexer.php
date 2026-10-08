<?php

namespace Ernestdefoe\Sonic\Search\Post;

use Ernestdefoe\Sonic\Search\AbstractIndexer;
use Flarum\Post\Post;
use Illuminate\Database\Eloquent\Builder;

/**
 * One object per visible comment, for post search. Only comments carry text;
 * event posts (renamed, stickied, …) are never sent.
 *
 * @extends AbstractIndexer<Post>
 */
class PostIndexer extends AbstractIndexer
{
    protected const FIELDS = ['content', 'hidden_at'];

    public static function index(): string
    {
        return 'posts';
    }

    protected function baseQuery(): Builder
    {
        return Post::query()->where('type', 'comment')->whereNull('hidden_at')->select('id', 'type', 'content', 'hidden_at');
    }

    public function save(array $models): void
    {
        parent::save(array_filter($models, fn ($p) => $p instanceof Post && $p->type === 'comment'));
    }

    protected function textFor(array $models): array
    {
        $texts = [];
        foreach ($models as $post) {
            if ($post->type === 'comment' && $post->hidden_at === null) {
                // The stored XML, not the accessor: no unparse per post.
                $texts[(int) $post->id] = (string) ($post->getAttributes()['content'] ?? '');
            }
        }

        return $texts;
    }
}
