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

        $parts = explode('.', $accessor);

        // filter / search / sort for list prices? => extend selector
        $listPrice = array_pop($parts) === 'listPrice';
        if ($listPrice) {
            $jsonAccessor = 'listPrice.' . $jsonAccessor;
        }

        $select = [];

        /*
         * It's not possible to cast to float/double, only decimal. But decimal has a fixed precision,
         * that would possibly result in rounding errors.
         *
         * We can indirectly cast to float by adding 0.0
         */

        $template = '(JSON_UNQUOTE(JSON_EXTRACT(`#root#`.`#field#`, "$.c#currencyId#.#property#")) #factor#)';

        $variables = [
            '#root#' => $root,
            '#field#' => $field->getStorageName(),
            '#currencyId#' => $context->getCurrencyId(),
            '#property#' => $jsonAccessor,
            '#factor#' => '+ 0.0',
        ];

        $select[] = str_replace(array_keys($variables), array_values($variables), $template);

        if ($context->getCurrencyId() !== Defaults::CURRENCY) {
            $variables = [
                '#root#' => $root,
                '#field#' => $field->getStorageName(),
                '#currencyId#' => Defaults::CURRENCY,
                '#property#' => $jsonAccessor,
                '#factor#' => sprintf('* %F', $context->getCurrencyFactor()),
            ];

            $select[] = str_replace(array_keys($variables), array_values($variables), $template);
        }

        $template = '(COALESCE(%s))';

        $variables = [
            '#template#' => $template,
            '#decimals#' => $context->getRounding()->getDecimals(),
        ];

        $template = str_replace(
            array_keys($variables),
            array_values($variables),
            '(ROUND(CAST(#template# as DECIMAL(30, 20)), #decimals#))'
        );

        if ($this->useCashRounding($context)) {
            $multiplier = 100 / ($context->getRounding()->getInterval() * 100);

            $variables = [
                '#accessor#' => $template,
                '#multiplier#' => $multiplier,
            ];

            $template = str_replace(array_keys($variables), array_values($variables), '(ROUND(#accessor# * #multiplier#, 0) / #multiplier#)');
        }

        return sprintf($template, implode(',', $select));
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
