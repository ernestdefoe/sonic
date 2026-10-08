<?php

namespace Ernestdefoe\Sonic;

/**
 * One Sonic Channel connection: a line-based TCP protocol, so a few dozen
 * lines of PHP replace a client library. Opened, used and closed within a
 * single operation — a held socket would outlive Sonic's idle timeout in a
 * long-running queue worker.
 *
 * Every argument that reaches the wire is either a constant, a sanitised
 * name (Sonic::name()) or text passed through Sonic::clean(), which strips
 * quotes, backslashes and line breaks — so nothing can close the quoted
 * string or start a second command.
 */
final class Channel
{
    /** @var resource */
    private $socket;

    /**
     * Longest line Sonic accepts, announced on START (20000 on stock builds).
     */
    private int $buffer = 20000;

    /** @param resource $socket */
    private function __construct($socket)
    {
        $this->socket = $socket;
    }

    /**
     * @throws SonicException unreachable | timeout | auth_failed | protocol
     */
    public static function open(string $host, int $port, string $mode, string $password, float $connect, float $timeout): self
    {
        // A bare IPv6 address needs brackets in a socket URI.
        $target = str_contains($host, ':') && ! str_starts_with($host, '[') ? "[$host]" : $host;

        $socket = @stream_socket_client("tcp://$target:$port", $errno, $errstr, $connect);
        if ($socket === false) {
            throw new SonicException('unreachable');
        }

        stream_set_timeout($socket, (int) $timeout, (int) (fmod($timeout, 1) * 1_000_000));

        $channel = new self($socket);

        if (! str_starts_with($channel->read(), 'CONNECTED')) {
            $channel->close();

            throw new SonicException('protocol');
        }

        $started = $channel->send(rtrim("START $mode $password"));

        if (! str_starts_with($started, 'STARTED')) {
            $channel->close();

            throw new SonicException(str_contains($started, 'authentication') ? 'auth_failed' : 'protocol');
        }

        if (preg_match('/buffer\((\d+)\)/', $started, $m)) {
            $channel->buffer = (int) $m[1];
        }

        return $channel;
    }

    /**
     * @return string[] object ids, best match first
     */
    public function query(string $collection, string $bucket, string $terms, int $limit, ?string $lang): array
    {
        $pending = $this->send("QUERY $collection $bucket \"$terms\" LIMIT($limit)".self::lang($lang));

        if (! preg_match('/^PENDING (\S+)$/', $pending, $m)) {
            throw new SonicException('protocol');
        }

        // The answer arrives as an event tagged with the marker.
        $event = "EVENT QUERY {$m[1]}";
        do {
            $line = $this->read();
        } while (! str_starts_with($line, $event) && ! str_starts_with($line, 'ERR'));

        if (str_starts_with($line, 'ERR')) {
            throw new SonicException('protocol');
        }

        return array_slice(explode(' ', $line), 3);
    }

    /**
     * PUSH adds terms to an object; it never replaces them. Text longer than
     * the announced buffer is split on word boundaries into several PUSHes.
     */
    public function push(string $collection, string $bucket, string $object, string $text, ?string $lang): void
    {
        $head = "PUSH $collection $bucket $object \"";
        $tail = '"'.self::lang($lang);
        $room = $this->buffer - strlen($head) - strlen($tail) - 2;

        while ($text !== '') {
            $chunk = $text;
            if (strlen($chunk) > $room) {
                // mb_strcut never splits a multi-byte character.
                $chunk = mb_strcut($text, 0, $room, 'UTF-8');
                $space = strrpos($chunk, ' ');
                if ($space !== false && $space > $room / 2) {
                    $chunk = substr($chunk, 0, $space);
                }
            }
            $text = ltrim(substr($text, strlen($chunk)));

            if ($this->send($head.$chunk.$tail) !== 'OK') {
                throw new SonicException('protocol');
            }
        }
    }

    /**
     * FLUSHO / FLUSHB / COUNTB — every one answers "RESULT <n>".
     */
    public function result(string $command): int
    {
        if (! preg_match('/^RESULT (\d+)$/', $this->send($command), $m)) {
            throw new SonicException('protocol');
        }

        return (int) $m[1];
    }

    public function send(string $line): string
    {
        if (@fwrite($this->socket, $line."\n") === false) {
            throw new SonicException('unreachable');
        }

        return $this->read();
    }

    /**
     * Just hangs up. Sonic treats end-of-stream as a clean close; sending
     * QUIT and not waiting for its "ENDED quit" made Sonic write to a closed
     * socket and panic a thread on every connection.
     */
    public function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    private function read(): string
    {
        $line = fgets($this->socket);

        if ($line === false) {
            throw new SonicException(stream_get_meta_data($this->socket)['timed_out'] ? 'timeout' : 'unreachable');
        }

        return rtrim($line, "\r\n");
    }

    private static function lang(?string $lang): string
    {
        return $lang === null ? '' : " LANG($lang)";
    }
}
