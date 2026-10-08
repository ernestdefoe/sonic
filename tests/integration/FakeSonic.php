<?php

namespace Ernestdefoe\Sonic\Tests\integration;

use Ernestdefoe\Sonic\Sonic;
use Ernestdefoe\Sonic\SonicException;

/**
 * Sonic with the server replaced by a script: a search answers with the ids
 * it is given (or null, as an unreachable server does), and an ingest
 * records that it was asked for without opening a socket.
 */
class FakeSonic extends Sonic
{
    /** @var list<int>|null */
    public ?array $hits = [];

    /** @var list<string> index of every search asked for */
    public array $searched = [];

    public int $ingests = 0;

    public function search(string $index, string $terms, ?string $lang = null): ?array
    {
        $this->searched[] = $index;

        return $this->hits;
    }

    public function ingest(callable $work): void
    {
        $this->ingests++;

        // Like an unreachable server, so the work itself is not run.
        throw new SonicException('down');
    }
}
