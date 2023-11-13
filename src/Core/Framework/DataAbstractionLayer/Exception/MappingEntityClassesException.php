<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('core')]
class MappingEntityClassesException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Mapping definition neither have entities nor collection.');
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__MAPPING_ENTITY_DEFINITION_CLASSES';
    }
}
