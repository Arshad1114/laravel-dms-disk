<?php

namespace Arshad1114\DmsDisk\Exceptions;

class DmsAuthException extends DmsException
{
    public static function invalidToken(): self
    {
        return new self('DMS API token is invalid or missing. Check your dms-disk config.');
    }
}
