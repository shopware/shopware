<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\ShopwareHttpException;

class ParentFieldForeignKeyConstraintMissingException extends ShopwareHttpException
{
    public function __construct(EntityDefinition $definition, Field $parentField)
    {
        parent::__construct(
            'Foreign key property {{ propertyName }} of parent association in definition {{ definition }} expected to be an FkField got %s',
            [
                'definition' => $definition->getClass(),
                'propertyName' => $parentField->getPropertyName(),
                'propertyClass' => get_class($parentField),
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__PARENT_FIELD_KEY_CONSTRAINT_MISSING';
    }
}
