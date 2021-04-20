<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class AdapterFactoryNotFoundException extends ShopwareHttpException
{
    public function __construct(string $type, ?\Throwable $previous = null)
    {
        parent::__construct(
            'Adapter factory for type "{{ type }}" was not found.',
            ['type' => $type],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__FILESYSTEM_ADAPTER_NOT_FOUND';
    }
}
