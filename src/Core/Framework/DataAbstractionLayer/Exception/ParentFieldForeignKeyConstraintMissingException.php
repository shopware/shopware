<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('core')]
class ParentFieldForeignKeyConstraintMissingException extends ShopwareHttpException
{
    public function __construct(
        EntityDefinition $definition,
        Field $parentField
    ) {
        parent::__construct(
            'Foreign key property {{ propertyName }} of parent association in definition {{ definition }} expected to be an FkField got %s',
            [
                'definition' => $definition->getEntityName(),
                'propertyName' => $parentField->getPropertyName(),
                'propertyClass' => $parentField::class,
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__PARENT_FIELD_KEY_CONSTRAINT_MISSING';
    }
}
