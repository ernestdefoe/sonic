<?php

namespace Ernestdefoe\Sonic;

use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Cache\Repository as Cache;
use Psr\Log\LoggerInterface;

/**
 * Settings, naming and the timeouts that keep a page fast when Sonic is not.
 *
 * 🚨 An unreachable Sonic must never hang a page. Connects give up after one
 * second, and the first failure is remembered for DOWN_TTL seconds so every
 * later search and save in that window skips Sonic at once instead of each
 * paying the timeout again. Searches then fall back to the database driver.
 */
class Sonic
{
    public const KEY = 'ernestdefoe-sonic';

    /**
     * Sonic's stock `query_limit_maximum`; asking for more is an error.
     */
    public const SEARCH_LIMIT = 100;

    protected const CONNECT_TIMEOUT = 1.0;
    protected const SEARCH_TIMEOUT = 2.0;
    protected const INGEST_TIMEOUT = 5.0;
    protected const DOWN_TTL = 30;

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected Cache $cache,
        protected LoggerInterface $log
    ) {
    }

    public function configured(): bool
    {
        return $this->host() !== '';
    }

    public function collection(string $index): string
    {
        return 'flarum_'.$index;
    }

    /**
     * One bucket per forum, so several forums can share one Sonic: a rebuild
     * flushes this bucket only, never the whole collection. Defaults to the
     * forum's host name.
     */
    public function bucket(): string
    {
        $bucket = (string) $this->settings->get(self::KEY.'.bucket', '');
        if (trim($bucket) === '') {
            $bucket = (string) (parse_url((string) $this->settings->get('forum_url', ''), PHP_URL_HOST) ?: 'flarum');
        }

        return self::name($bucket);
    }

    /**
     * @return int[]|null ids best first; null when Sonic could not answer
     */
    public function search(string $index, string $terms, ?string $lang = null): ?array
    {
        if (! $this->configured()) {
            return null;
        }

        $terms = mb_strcut(self::clean($terms), 0, 500, 'UTF-8');
        if ($terms === '') {
            return [];
        }

        try {
            $channel = $this->open('search', self::SEARCH_TIMEOUT);
            $ids = $channel->query($this->collection($index), $this->bucket(), $terms, self::SEARCH_LIMIT, $lang);
            $channel->close();
        } catch (SonicException $e) {
            $this->fail($e, "search $index");

            return null;
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    /**
     * Runs $work on one ingest connection.
     *
     * @param callable(Channel): void $work
     * @throws SonicException
     */
    public function ingest(callable $work): void
    {
        $channel = null;

        try {
            $channel = $this->open('ingest', self::INGEST_TIMEOUT);
            $work($channel);
        } catch (SonicException $e) {
            $this->fail($e, 'ingest');
            throw $e;
        } finally {
            $channel?->close();
        }
    }

    /**
     * Asks Sonic to fold fresh words into its typo/prefix graph now rather
     * than on its next timer, so a rebuilt index answers fuzzy queries at once.
     */
    public function consolidate(): void
    {
        try {
            $channel = $this->open('control', self::INGEST_TIMEOUT);
            $channel->send('TRIGGER consolidate');
            $channel->close();
        } catch (SonicException $e) {
            $this->fail($e, 'consolidate');
        }
    }

    /**
     * For the admin status button: ignores (and clears) the remembered
     * failure, so a fixed connection shows as fixed straight away.
     *
     * @param string[] $indexes
     * @return array{ok: bool, error?: string, counts?: array<string, int>}
     */
    public function status(array $indexes): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'error' => 'not_configured'];
        }

        $this->cache->forget($this->downKey());

        try {
            $counts = [];
            $this->ingest(function (Channel $channel) use ($indexes, &$counts) {
                foreach ($indexes as $index) {
                    $counts[$index] = $channel->result("COUNTB {$this->collection($index)} {$this->bucket()}");
                }
            });

            return ['ok' => true, 'counts' => $counts];
        } catch (SonicException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Text fit for a quoted Sonic argument: markup and entities gone, and
     * quotes, backslashes and control characters (line breaks included)
     * turned into spaces. Sonic's tokenizer drops punctuation anyway, so
     * nothing searchable is lost.
     */
    public static function clean(string $text): string
    {
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/[\s"\\\\\x00-\x1F\x7F]+/u', ' ', $text);

        return trim($text);
    }

    /**
     * Collection, bucket and object names: 1–128 ASCII characters, no spaces.
     */
    public static function name(string $value): string
    {
        return substr((string) preg_replace('/[^A-Za-z0-9_.:-]/', '_', $value), 0, 128) ?: 'flarum';
    }

    protected function open(string $mode, float $timeout): Channel
    {
        if ($this->cache->get($this->downKey())) {
            throw new SonicException('down');
        }

        return Channel::open($this->host(), $this->port(), $mode, $this->password(), self::CONNECT_TIMEOUT, $timeout);
    }

    protected function fail(SonicException $e, string $what): void
    {
        if ($e->getMessage() === 'down') {
            return;
        }

        if ($e->unreachable()) {
            $this->cache->put($this->downKey(), true, self::DOWN_TTL);
        }

        $this->log->warning("[sonic] $what failed: ".$e->getMessage());
    }

    protected function host(): string
    {
        return trim((string) $this->settings->get(self::KEY.'.host', ''));
    }

    protected function port(): int
    {
        $port = (int) $this->settings->get(self::KEY.'.port', 1491);

        return $port > 0 && $port < 65536 ? $port : 1491;
    }

    /**
     * Sonic splits START on whitespace, so a password can hold none.
     */
    protected function password(): string
    {
        return (string) preg_replace('/\s+/', '', (string) $this->settings->get(self::KEY.'.password', ''));
    }

    protected function downKey(): string
    {
        return self::KEY.'.down';
    }
}
