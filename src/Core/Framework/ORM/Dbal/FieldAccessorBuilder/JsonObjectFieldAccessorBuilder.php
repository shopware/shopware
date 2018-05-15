<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Dbal\FieldAccessorBuilder;

use Shopware\Framework\ORM\Field\Field;
use Shopware\Framework\ORM\Field\JsonObjectField;
use Shopware\Framework\ORM\Write\FieldAware\StorageAware;
use Shopware\Application\Context\Struct\ApplicationContext;

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
