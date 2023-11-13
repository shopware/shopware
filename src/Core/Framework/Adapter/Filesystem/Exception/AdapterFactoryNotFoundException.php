<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('core')]
class AdapterFactoryNotFoundException extends ShopwareHttpException
{
    public function __construct(string $type)
    {
        parent::__construct(
            'Adapter factory for type "{{ type }}" was not found.',
            ['type' => $type]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__FILESYSTEM_ADAPTER_NOT_FOUND';
    }
}
