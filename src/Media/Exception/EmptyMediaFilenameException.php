<?php

namespace Shopware\Media\Exception;

class EmptyMediaFilenameException extends \RuntimeException
{
    public $message = 'A valid path must be provided.';
}
