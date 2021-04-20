<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\ShopwareHttpException;

class CanNotFindParentStorageFieldException extends ShopwareHttpException
{
    public function __construct(EntityDefinition $definition, ?\Throwable $previous = null)
    {
        parent::__construct(
            'Can not find FkField for parent property definition {{ definition }}',
            ['definition' => $definition->getClass()],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__CAN_NOT_FIND_PARENT_STORAGE_FIELD';
    }
}
