<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Exception;

use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class AdapterFactoryNotFoundException extends AdapterException
{
    public function __construct(string $type)
    {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'FRAMEWORK__FILESYSTEM_ADAPTER_NOT_FOUND',
            'Adapter factory for type "{{ type }}" was not found.',
            ['type' => $type]
        );
    }
}
