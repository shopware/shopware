<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('core')]
class CanNotFindParentStorageFieldException extends ShopwareHttpException
{
    public function __construct(EntityDefinition $definition)
    {
        parent::__construct(
            'Can not find FkField for parent property definition {{ definition }}',
            ['definition' => $definition->getEntityName()]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__CAN_NOT_FIND_PARENT_STORAGE_FIELD';
    }
}
