<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('core')]
class InvalidParentAssociationException extends ShopwareHttpException
{
    public function __construct(
        EntityDefinition $definition,
        Field $parentField
    ) {
        parent::__construct(
            'Parent property for {{ definition }} expected to be an ManyToOneAssociationField got {{ fieldDefinition }}',
            ['definition' => $definition->getEntityName(), 'fieldDefinition' => $parentField::class]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_PARENT_ASSOCIATION_EXCEPTION';
    }
}
