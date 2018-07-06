<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

class IllegalMimeTypeException extends \RuntimeException
{
    public function __construct(string $MimeType)
    {
        parent::__construct("Mime-type '{$MimeType}' is not supported by this action");
    }
}
