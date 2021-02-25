<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\FieldAccessorBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;

class CheapestPriceAccessorBuilder implements FieldAccessorBuilderInterface
{
    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): ?string
    {
        if (!$field instanceof CheapestPriceField) {
            return null;
        }

        // cheapest price is only indexed for parent product
        $keys = $context->getRuleIds();
        $keys[] = 'default';

        $taxMode = 'net';
        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            $taxMode = 'gross';
        }

        $template = '(JSON_UNQUOTE(JSON_EXTRACT(`#root#`.`#field#`, "$.#rule_key#.#currency_key#.#tax_mode#")) * #factor#)';
        $variables = [
            '#template#' => $template,
            '#decimals#' => $context->getRounding()->getDecimals(),
        ];

        $template = str_replace(
            array_keys($variables),
            array_values($variables),
            '(ROUND(CAST(#template# as DECIMAL(30, 20)), #decimals#))'
        );

        $multiplier = '';
        if ($this->useCashRounding($context)) {
            $multiplier = 100 / ($context->getRounding()->getInterval() * 100);
            $template = '(ROUND(' . $template . ' * #multiplier#, 0) / #multiplier#)';
        }

        $select = [];

        foreach ($keys as $ruleId) {
            $parameters = [
                '#root#' => $root,
                '#field#' => 'cheapest_price_accessor',
                '#rule_key#' => 'rule' . $ruleId,
                '#currency_key#' => 'currency' . $context->getCurrencyId(),
                '#tax_mode#' => $taxMode,
                '#factor#' => 1,
                '#multiplier#' => $multiplier,
            ];

            $select[] = str_replace(
                array_keys($parameters),
                array_values($parameters),
                $template
            );

            if ($context->getCurrencyId() === Defaults::CURRENCY) {
                continue;
            }

            $parameters = [
                '#root#' => $root,
                '#field#' => 'cheapest_price_accessor',
                '#rule_key#' => 'rule' . $ruleId,
                '#currency_key#' => 'currency' . Defaults::CURRENCY,
                '#tax_mode#' => $taxMode,
                '#factor#' => $context->getCurrencyFactor(),
                '#multiplier#' => $multiplier,
            ];

            $select[] = str_replace(
                array_keys($parameters),
                array_values($parameters),
                $template
            );
        }

        return sprintf('COALESCE(%s)', implode(',', $select));
    }

    private function useCashRounding(Context $context): bool
    {
        if ($context->getRounding()->getDecimals() !== 2) {
            return false;
        }

        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            return true;
        }

        return $context->getRounding()->roundForNet();
    }
}
