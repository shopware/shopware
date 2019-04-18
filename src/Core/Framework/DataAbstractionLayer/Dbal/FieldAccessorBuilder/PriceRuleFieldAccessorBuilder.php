<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceRulesJsonField;

class PriceRuleFieldAccessorBuilder implements FieldAccessorBuilderInterface
{
    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): ?string
    {
        if (!$field instanceof PriceRulesJsonField) {
            return null;
        }

        $keys = $context->getRules();

        $jsonField = 'net';
        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            $jsonField = 'gross';
        }

        $select = [];
        foreach ($keys as $key) {
            $parsed = sprintf('`%s`.`%s`', $root, $field->getStorageName());
            $path = sprintf('$.optimized.r%s.c%s.%s', $key, $context->getCurrencyId(), $jsonField);
            $select[] = sprintf('JSON_UNQUOTE(JSON_EXTRACT(%s, "%s"))', $parsed, $path);

            if ($context->getCurrencyId() !== Defaults::CURRENCY) {
                $path = sprintf('$.optimized.r%s.c%s.%s', $key, Defaults::CURRENCY, $jsonField);
                $select[] = sprintf('JSON_UNQUOTE(JSON_EXTRACT(%s, "%s")) * %s', $parsed, $path, $context->getCurrencyFactor());
            }
        }

        $parsed = sprintf('`%s`.`%s`', $root, 'price');
        $select[] = sprintf('JSON_UNQUOTE(JSON_EXTRACT(%s, "%s"))', $parsed, '$.' . $jsonField);

        return sprintf('(CAST(COALESCE(%s) AS DECIMAL))', implode(',', $select));
    }
}
