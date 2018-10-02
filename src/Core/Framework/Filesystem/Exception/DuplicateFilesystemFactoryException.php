<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Filesystem\Exception;

class DuplicateFilesystemFactoryException extends \Exception
{
    public static function fromAdapterType(string $type): self
    {
        return new self(sprintf('The type of factory "%s" must be unique.', $type));
    }
}
