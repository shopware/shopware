<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class MappingEntityClassesException extends ShopwareHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Mapping definition neither have entities nor collection.', [], $previous);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__MAPPING_ENTITY_DEFINITION_CLASSES';
    }
}
