<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('core')]
class WriteTypeIntendException extends ShopwareHttpException
{
    public function __construct(
        EntityDefinition $definition,
        string $expectedClass,
        string $actualClass
    ) {
        parent::__construct(
            'Expected command for "{{ definition }}" to be "{{ expectedClass }}". (Got: {{ actualClass }})',
            ['definition' => $definition->getEntityName(), 'expectedClass' => $expectedClass, 'actualClass' => $actualClass]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__WRITE_TYPE_INTEND_ERROR';
    }
}
