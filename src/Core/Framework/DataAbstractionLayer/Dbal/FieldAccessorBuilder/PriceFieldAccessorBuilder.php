<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;

class PriceFieldAccessorBuilder implements FieldAccessorBuilderInterface
{
    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): ?string
    {
        if (!$field instanceof PriceField) {
            return null;
        }

        /*
         * It's not possible to cast to float/double, only decimal. But decimal has a fixed precision,
         * that would possibly result in rounding errors.
         *
         * We can indirectly cast to float by adding 0.0
         */
        return sprintf('(JSON_UNQUOTE(JSON_EXTRACT(`%s`.`%s`, "$.gross")) + 0.0)', $root, $field->getStorageName());
    }
}
