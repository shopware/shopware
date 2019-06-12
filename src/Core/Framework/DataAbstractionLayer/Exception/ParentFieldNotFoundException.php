<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\ShopwareHttpException;

class ParentFieldNotFoundException extends ShopwareHttpException
{
    public function __construct(EntityDefinition $definition)
    {
        parent::__construct(
            'Can not find parent property \'parent\' field for definition {{ definition }',
            ['definition' => $definition->getClass()]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__PARENT_FIELD_NOT_FOUND_EXCEPTION';
    }
}
