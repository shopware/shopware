<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Defaults;
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

        $jsonAccessor = 'net';
        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            $jsonAccessor = 'gross';
        }

        $select = [];

        /*
         * It's not possible to cast to float/double, only decimal. But decimal has a fixed precision,
         * that would possibly result in rounding errors.
         *
         * We can indirectly cast to float by adding 0.0
         */
        $select[] = sprintf(
            '(JSON_UNQUOTE(JSON_EXTRACT(`%s`.`%s`, "$.%s.%s")) %s)',
            $root,
            $field->getStorageName(),
            'c' . $context->getCurrencyId(),
            $jsonAccessor,
            '+ 0.0'
        );

        if ($context->getCurrencyId() !== Defaults::CURRENCY) {
            $currencyFactor = sprintf('* %F', $context->getCurrencyFactor());

            $select[] = sprintf(
                '(JSON_UNQUOTE(JSON_EXTRACT(`%s`.`%s`, "$.%s.%s")) %s)',
                $root,
                $field->getStorageName(),
                'c' . Defaults::CURRENCY,
                $jsonAccessor,
                $currencyFactor
            );
        }

        return sprintf('(COALESCE(%s))', implode(',', $select));
    }
}
