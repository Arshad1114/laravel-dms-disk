<?php

namespace Arshad1114\DmsDisk\Exceptions;

class DmsConnectionException extends DmsException
{
    public static function timeout(string $url): self
    {
        return new self("DMS connection timed out while reaching: {$url}");
    }

    public static function unreachable(string $url, string $reason = ''): self
    {
        $msg = "DMS service unreachable at: {$url}";
        return new self($reason ? "{$msg}. Reason: {$reason}" : $msg);
    }
}
