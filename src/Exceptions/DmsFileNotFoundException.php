<?php

namespace Arshad1114\DmsDisk\Exceptions;

class DmsFileNotFoundException extends DmsException
{
    public static function atPath(string $path): self
    {
        return new self("File not found on DMS at path: {$path}");
    }
}
