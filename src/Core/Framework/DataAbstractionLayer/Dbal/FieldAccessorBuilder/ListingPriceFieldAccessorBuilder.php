<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListingPriceField;

class ListingPriceFieldAccessorBuilder implements FieldAccessorBuilderInterface
{
    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): ?string
    {
        if (!$field instanceof ListingPriceField) {
            return null;
        }

        $keys = $context->getRuleIds();

        $taxMode = 'net';
        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            $taxMode = 'gross';
        }

        $template = '((ROUND(CAST((JSON_UNQUOTE(JSON_EXTRACT(`#root#`.`#field#`, "$.#rule_key#.#currency_key#.to.#tax_mode#"))) as DECIMAL(30, 20)), #decimals#)) * #factor#)';

        $multiplier = null;
        if ($this->useCashRounding($context)) {
            $multiplier = 100 / ($context->getRounding()->getInterval() * 100);
            $template = '(ROUND(' . $template . ' * #multiplier#, 0) / #multiplier#)';
        }

        $parameters = [
            '#root#' => $root,
            '#field#' => $field->getStorageName(),
            '#tax_mode#' => $taxMode,
            '#decimals#' => $context->getRounding()->getDecimals(),
        ];

        if ($multiplier !== null) {
            $parameters['#multiplier#'] = $multiplier;
        }

        $template = str_replace(
            array_keys($parameters),
            array_values($parameters),
            $template
        );

        foreach ($keys as $ruleId) {
            $select[] = $this->getSelect($template, 'r' . $ruleId, $context->getCurrencyId(), 1);

            if ($context->getCurrencyId() === Defaults::CURRENCY) {
                continue;
            }

            $select[] = $this->getSelect($template, 'r' . $ruleId, Defaults::CURRENCY, $context->getCurrencyFactor());
        }

        $select[] = $this->getSelect($template, 'default', $context->getCurrencyId(), 1);
        if ($context->getCurrencyId() !== Defaults::CURRENCY) {
            $select[] = $this->getSelect($template, 'default', Defaults::CURRENCY, $context->getCurrencyFactor());
        }

        return sprintf('COALESCE(%s)', implode(',', $select));
    }

    private function getSelect(string $template, string $ruleKey, string $currencyId, float $factor): string
    {
        $parameters = [
            '#rule_key#' => $ruleKey,
            '#currency_key#' => 'c' . $currencyId,
            '#factor#' => $factor,
        ];

        return str_replace(
            array_keys($parameters),
            array_values($parameters),
            $template
        );
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
