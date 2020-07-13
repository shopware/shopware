<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListingPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;

class ListingPriceFieldAccessorBuilder implements FieldAccessorBuilderInterface
{
    /**
     * @var PriceFieldAccessorBuilder
     */
    private $priceFieldAccessor;

    public function __construct(PriceFieldAccessorBuilder $priceFieldAccessor)
    {
        $this->priceFieldAccessor = $priceFieldAccessor;
    }

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

        foreach ($keys as $ruleId) {
            $parameters = [
                '#root#' => $root,
                '#field#' => $field->getStorageName(),
                '#rule_key#' => 'r' . $ruleId,
                '#currency_key#' => 'c' . $context->getCurrencyId(),
                '#tax_mode#' => $taxMode,
            ];

            $select[] = str_replace(
                array_keys($parameters),
                array_values($parameters),
                '(JSON_UNQUOTE(JSON_EXTRACT(`#root#`.`#field#`, "$.formatted.#rule_key#.#currency_key#.to.#tax_mode#")) + 0.0)'
            );
        }

        $select[] = $this->priceFieldAccessor
            ->buildAccessor($root, new PriceField('price', 'price'), $context, '');

        return sprintf('COALESCE(%s)', implode(',', $select));
    }
}
