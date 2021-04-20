<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class DuplicateFilesystemFactoryException extends ShopwareHttpException
{
    public function __construct(string $type, ?\Throwable $previous = null)
    {
        parent::__construct('The type of factory "{{ type }}" must be unique.', ['type' => $type], $previous);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__DUPLICATE_FILESYSTEM_FACTORY';
    }
}
