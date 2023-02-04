<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\FieldAccessorBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class CheapestPriceAccessorBuilder implements FieldAccessorBuilderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly int $maxRulePrices,
        private readonly LoggerInterface $logger
    ) {
    }

    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): ?string
    {
        if (!$field instanceof CheapestPriceField) {
            return null;
        }

        // cheapest price is only indexed for parent product
        $keys = $context->getRuleIds();
        if (\count($keys) > $this->maxRulePrices) {
            $this->logger->warning(\sprintf('More than %d rules are active, only the first %d rules are considered for the cheapest price calculation', $this->maxRulePrices, $this->maxRulePrices));
            $this->logger->info(
                \sprintf('More rules then the configured `dal.max_rule_prices` are active, thus not all rule prices are considered for the cheapest price. You can increase the `dal.max_rule_prices`, but this will have a negative performance impact. Consider restructuring your rules, so that not so many match at the same time.')
            );
            $keys = \array_slice($keys, 0, $this->maxRulePrices);
        }
        $keys[] = 'default';

        $jsonAccessor = 'net';
        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            $jsonAccessor = 'gross';
        }

        $parts = explode('.', $accessor);

        // is tax state explicitly requested? => overwrite selector
        if (\in_array(end($parts), ['net', 'gross'], true)) {
            $jsonAccessor = end($parts);
            array_pop($parts);
        }

        // filter / search / sort for list prices? => extend selector
        if (end($parts) === 'listPrice') {
            $jsonAccessor = 'listPrice.' . $jsonAccessor;
            array_pop($parts);
        }

        if (end($parts) === 'percentage') {
            $jsonAccessor = 'percentage.' . $jsonAccessor;
            array_pop($parts);
        }

        $template = '(JSON_UNQUOTE(JSON_EXTRACT(`#root#`.`#field#`, "$.#rule_key#.#currency_key#.#property#")) * #factor#)';
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
                '#property#' => $jsonAccessor,
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
                '#property#' => $jsonAccessor,
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
