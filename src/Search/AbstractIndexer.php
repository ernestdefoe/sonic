<?php

namespace Ernestdefoe\Sonic\Search;

use Ernestdefoe\Sonic\Channel;
use Ernestdefoe\Sonic\Sonic;
use Ernestdefoe\Sonic\SonicException;
use Flarum\Database\AbstractModel;
use Flarum\Search\IndexerInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * One Sonic collection per resource, one bucket per forum, one object per
 * model id. Sonic has no update: an object is flushed, then pushed again.
 *
 * 🚨 Core queues an index job on EVERY model update — a user's last-seen
 * time, a discussion's reply counter. On the sync queue those run inside the
 * visitor's request, so save() first drops models whose indexed text did not
 * change and only opens a connection when something is left.
 */
abstract class AbstractIndexer implements IndexerInterface
{
    protected const CHUNK = 500;

    /**
     * Cap on the text kept per object, in bytes.
     */
    public const MAX_TEXT = 60000;

    /**
     * Columns whose change means the object must be re-pushed.
     */
    protected const FIELDS = [];

    /** Sonic LANG(); null lets Sonic detect the language. */
    protected const LANG = null;

    public function __construct(
        protected Sonic $sonic
    ) {
    }

    abstract protected function baseQuery(): Builder;

    /**
     * @param AbstractModel[] $models
     * @return array<int, string> id => text, only for models that belong in the index
     */
    abstract protected function textFor(array $models): array;

    public function save(array $models): void
    {
        $this->reindex(array_filter($models, fn (AbstractModel $m) => $this->changed($m)));
    }

    /**
     * Re-push these models unconditionally.
     *
     * @param AbstractModel[] $models
     */
    public function reindex(array $models): void
    {
        if (! $models || ! $this->sonic->configured()) {
            return;
        }

        $this->safely(function (Channel $channel) use ($models) {
            $texts = $this->textFor($models);
            foreach ($models as $model) {
                // A model created in this request has no object to flush yet.
                if (! $model->wasRecentlyCreated) {
                    $channel->result($this->command('FLUSHO', $model->id));
                }
                $this->pushText($channel, $model->id, $texts[$model->id] ?? '');
            }
        });
    }

    public function delete(array $models): void
    {
        if (! $models || ! $this->sonic->configured()) {
            return;
        }

        $this->safely(function (Channel $channel) use ($models) {
            foreach ($models as $model) {
                $channel->result($this->command('FLUSHO', $model->id));
            }
        });
    }

    /**
     * Flushes this forum's bucket and pushes everything again, on one
     * connection. Errors propagate so the console and job report them.
     */
    public function build(): void
    {
        if (! $this->sonic->configured()) {
            return;
        }

        $this->sonic->ingest(function (Channel $channel) {
            $channel->result($this->command('FLUSHB'));

            $this->baseQuery()->chunkById(static::CHUNK, function ($models) use ($channel) {
                foreach ($this->textFor($models->all()) as $id => $text) {
                    $this->pushText($channel, $id, $text);
                }
            });
        });
    }

    public function flush(): void
    {
        if ($this->sonic->configured()) {
            $this->sonic->ingest(fn (Channel $channel) => $channel->result($this->command('FLUSHB')));
        }
    }

    /**
     * A model loaded fresh (no recorded changes) or just created always
     * counts; an update counts only when an indexed column moved.
     */
    protected function changed(AbstractModel $model): bool
    {
        return $model->wasRecentlyCreated || ! $model->getChanges() || $model->wasChanged(static::FIELDS);
    }

    protected function pushText(Channel $channel, int|string $id, string $text): void
    {
        $text = mb_strcut(Sonic::clean($text), 0, static::MAX_TEXT, 'UTF-8');
        if ($text !== '') {
            $channel->push($this->sonic->collection(static::index()), $this->sonic->bucket(), (string) (int) $id, $text, static::LANG);
        }
    }

    protected function command(string $verb, int|string|null $id = null): string
    {
        return trim("$verb {$this->sonic->collection(static::index())} {$this->sonic->bucket()} ".($id === null ? '' : (int) $id));
    }

    /**
     * Index upkeep never breaks a save: a failure is logged (and an
     * unreachable server remembered) by Sonic::ingest(), then dropped. A
     * rebuild repairs anything missed while Sonic was away.
     */
    protected function safely(callable $work): void
    {
        try {
            $this->sonic->ingest($work);
        } catch (SonicException) {
        }
    }
}
