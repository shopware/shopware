<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

class UploadException extends \RuntimeException
{
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}
