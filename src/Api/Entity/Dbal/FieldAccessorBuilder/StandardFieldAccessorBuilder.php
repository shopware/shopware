<?php

namespace Shopware\Api\Entity\Dbal\FieldAccessorBuilder;

use Shopware\Api\Entity\Dbal\EntityDefinitionQueryHelper;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Write\FieldAware\StorageAware;
use Shopware\Context\Struct\ApplicationContext;

class StandardFieldAccessorBuilder implements FieldAccessorBuilderInterface
{
    public function buildAccessor(string $root, Field $field, ApplicationContext $context, string $accessor): string
    {
        if (!$field instanceof StorageAware) {
            return null;
        }

        return EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName());
    }
}