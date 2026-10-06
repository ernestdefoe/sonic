<?php

namespace Ernestdefoe\Sonic\Search\Discussion;

use Ernestdefoe\Sonic\Channel;
use Ernestdefoe\Sonic\Sonic;
use Ernestdefoe\Sonic\SonicException;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Search\IndexerInterface;

/**
 * A post's text is part of its discussion's object. A NEW reply is simply
 * pushed onto that object (PUSH appends), so replying never re-reads the
 * thread. An edit, hide or delete re-pushes the discussion from the database,
 * because Sonic cannot take individual words back out. The discussions
 * bucket itself is owned by DiscussionIndexer, so build/flush do nothing here.
 */
class PostReindexer implements IndexerInterface
{
    public function __construct(
        protected Sonic $sonic,
        protected DiscussionIndexer $discussions
    ) {
    }

    public static function index(): string
    {
        return 'discussions';
    }

    /**
     * @param Post[] $models
     */
    public function save(array $models): void
    {
        $append = [];
        $rebuild = [];

        foreach ($models as $post) {
            if ($post->type !== 'comment') {
                continue;
            }
            if ($post->wasRecentlyCreated && $post->hidden_at === null) {
                $append[(int) $post->discussion_id][] = $post->getAttributes()['content'] ?? '';
            } elseif (! $post->getChanges() || $post->wasChanged(['content', 'hidden_at'])) {
                $rebuild[] = (int) $post->discussion_id;
            }
        }

        $this->rebuild($rebuild);

        if ($append && $this->sonic->configured()) {
            try {
                $this->sonic->ingest(function (Channel $channel) use ($append) {
                    foreach ($append as $id => $texts) {
                        $text = mb_strcut(Sonic::clean(implode(' ', $texts)), 0, DiscussionIndexer::MAX_TEXT, 'UTF-8');
                        if ($text !== '') {
                            $channel->push($this->sonic->collection('discussions'), $this->sonic->bucket(), (string) $id, $text, null);
                        }
                    }
                });
            } catch (SonicException) {
                // Logged by Sonic::ingest(); a rebuild repairs it.
            }
        }
    }

    /**
     * @param Post[] $models
     */
    public function delete(array $models): void
    {
        $this->rebuild(array_map(fn (Post $p) => (int) $p->discussion_id, $models));
    }

    public function build(): void
    {
    }

    public function flush(): void
    {
    }

    /**
     * @param int[] $ids
     */
    protected function rebuild(array $ids): void
    {
        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids && $this->sonic->configured()) {
            $this->discussions->reindex(Discussion::query()->whereIn('id', $ids)->get()->all());
        }
    }
}
