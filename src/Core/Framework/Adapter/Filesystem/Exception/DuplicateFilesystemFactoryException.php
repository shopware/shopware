<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Exception;

use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class DuplicateFilesystemFactoryException extends AdapterException
{
    public function __construct(string $type)
    {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'FRAMEWORK__DUPLICATE_FILESYSTEM_FACTORY',
            'The type of factory "{{ type }}" must be unique.',
            ['type' => $type]
        );
    }
}
