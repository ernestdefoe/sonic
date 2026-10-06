<?php

namespace Ernestdefoe\Sonic;

use RuntimeException;

/**
 * The message is a short code (unreachable, timeout, auth_failed, protocol,
 * down) that the admin UI translates — never server output or a password.
 */
class SonicException extends RuntimeException
{
    public function unreachable(): bool
    {
        return in_array($this->getMessage(), ['unreachable', 'timeout', 'down'], true);
    }
}
