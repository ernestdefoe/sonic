<?php

namespace Ernestdefoe\Sonic\Search;

use Ernestdefoe\Sonic\Sonic;
use Flarum\Search\AbstractFulltextFilter;
use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\SearchState;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;

/**
 * Asks Sonic for matching ids (best first), narrows the searcher's query to
 * them and keeps Sonic's order as the default sort.
 *
 * Permissions never depend on Sonic: the query arriving here already carries
 * whereVisibleTo for the actor (private discussions, hidden and unapproved
 * posts, restricted tags), so an id the actor may not see is simply never
 * loaded. When Sonic cannot answer, the core database fulltext filter runs
 * instead — a search box never breaks because the search server did.
 */
abstract class SonicFulltextFilter extends AbstractFulltextFilter
{
    public function __construct(
        protected Sonic $sonic,
        protected Container $container
    ) {
    }

    /** Index name: discussions, posts or users. */
    abstract protected function index(): string;

    /** Core's database fulltext filter, used when Sonic is unavailable. */
    abstract protected function fallback(): string;

    /** Sonic LANG() for queries; null lets Sonic detect the language. */
    protected function lang(): ?string
    {
        return null;
    }

    protected function matched(Builder $query): void
    {
    }

    public function search(SearchState $state, string $value): void
    {
        /** @var DatabaseSearchState $state */
        $ids = $this->sonic->search($this->index(), $value, $this->lang());

        if ($ids === null) {
            $this->container->make($this->fallback())->search($state, $value);

            return;
        }

        $query = $state->getQuery();

        if ($ids === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $column = $query->getModel()->getTable().'.id';
        $query->whereIn($column, $ids);
        $this->matched($query);

        // 🚨 Wrapped through the grammar: raw SQL skips the table prefix.
        $order = 'CASE '.$query->getGrammar()->wrap($column).str_repeat(' WHEN ? THEN ?', count($ids)).' END';
        $bindings = [];
        foreach ($ids as $rank => $id) {
            array_push($bindings, $id, $rank);
        }

        $state->setDefaultSort(fn (Builder $q) => $q->orderByRaw($order, $bindings));
    }
}
