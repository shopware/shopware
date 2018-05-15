<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Dbal\FieldAccessorBuilder;

use Shopware\Framework\ORM\Dbal\EntityDefinitionQueryHelper;
use Shopware\Framework\ORM\Field\Field;
use Shopware\Framework\ORM\Write\FieldAware\StorageAware;
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
