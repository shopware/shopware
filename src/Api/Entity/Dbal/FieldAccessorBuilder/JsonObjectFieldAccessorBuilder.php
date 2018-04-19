<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Dbal\FieldAccessorBuilder;

use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Field\JsonObjectField;
use Shopware\Api\Entity\Write\FieldAware\StorageAware;
use Shopware\Context\Struct\ApplicationContext;

class JsonObjectFieldAccessorBuilder implements FieldAccessorBuilderInterface
{
    public function buildAccessor(string $root, Field $field, ApplicationContext $context, string $accessor): ?string
    {
        /** @var StorageAware $field */
        if (!$field instanceof JsonObjectField) {
            return null;
        }

        $accessor = str_replace($field->getPropertyName() . '.', '', $accessor);

        return sprintf(
            'JSON_UNQUOTE(JSON_EXTRACT(`%s`.`%s`, "$.%s"))',
            $root,
            $field->getPropertyName(),
            $accessor
        );
    }
}
