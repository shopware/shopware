<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\StorageAware;

class JsonFieldAccessorBuilder implements FieldAccessorBuilderInterface
{
    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): ?string
    {
        /** @var StorageAware $field */
        if (!$field instanceof JsonField) {
            return null;
        }

        $accessor = preg_replace('#^' . $field->getPropertyName() . '#', '', $accessor);

        return sprintf(
            'JSON_UNQUOTE(JSON_EXTRACT(`%s`.`%s`, "$%s"))',
            $root,
            $field->getStorageName(),
            $accessor
        );
    }
}
