<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\ShopwareHttpException;

class InvalidParentAssociationException extends ShopwareHttpException
{
    public function __construct(EntityDefinition $definition, Field $parentField)
    {
        parent::__construct(
            'Parent property for {{ definition }} expected to be an ManyToOneAssociationField got {{ fieldDefinition }}',
            ['definition' => $definition->getClass(), 'fieldDefinition' => \get_class($parentField)]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_PARENT_ASSOCIATION_EXCEPTION';
    }
}
