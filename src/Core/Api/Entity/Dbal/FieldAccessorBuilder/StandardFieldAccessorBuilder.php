<?php declare(strict_types=1);

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
            throw new \RuntimeException('Only storage aware fields can be accessed');
        }

        return EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName());
    }
}
