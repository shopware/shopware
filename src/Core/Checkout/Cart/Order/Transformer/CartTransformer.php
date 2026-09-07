<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order\Transformer;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\PriceModifier\PriceModifier;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\AdminSalesChannelApiSource;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterNameChange;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Clock\Clock;

#[Package('checkout')]
class CartTransformer
{
    /**
     * @return array<string, mixed>
     */
    #[ParameterNameChange(version: 'v6.8.0', parameterName: 'setOrderDate', newName: 'setPersistentData')]
    public static function transform(Cart $cart, SalesChannelContext $context, string $stateId, bool $setOrderDate = true): array
    {
        $currency = $context->getCurrency();

        $data = [
            'price' => $cart->getPrice(),
            'shippingCosts' => $cart->getShippingCosts(),
            'stateId' => $stateId,
            'currencyId' => $currency->getId(),
            'currencyFactor' => $currency->getFactor(),
            'salesChannelId' => $context->getSalesChannelId(),
            'lineItems' => [],
            'deliveries' => [],
            'customerComment' => $cart->getCustomerComment(),
            'affiliateCode' => $cart->getAffiliateCode(),
            'campaignCode' => $cart->getCampaignCode(),
            'source' => $cart->getSource(),
        ];

        // Seeded only at initial placement (no ORIGINAL_ID extension yet) -- once an order exists,
        // OrderPriceModificationCollector/Processor are the sole source of truth, and rebuilding this
        // on every recalculation would overwrite an admin's live edits.
        if (!$cart->hasExtensionOfType(OrderConverter::ORIGINAL_ID, IdStruct::class)) {
            $data['priceModifications'] = self::transformPriceModifications($cart->getPriceModifiers()->getElements());
        }

        if ($setOrderDate) {
            $data['orderDateTime'] = Clock::get()->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $data['deepLinkCode'] = Random::getBase64UrlString(32);
        }

        $source = $context->getContext()->getSource();
        if ($source instanceof AdminSalesChannelApiSource) {
            $originalContextSource = $source->getOriginalContext()->getSource();
            if ($originalContextSource instanceof AdminApiSource) {
                $data['createdById'] = $originalContextSource->getUserId();
            }
        }

        $data['itemRounding'] = json_decode(Json::encode($context->getItemRounding()), true, 512, \JSON_THROW_ON_ERROR);
        $data['totalRounding'] = json_decode(Json::encode($context->getTotalRounding()), true, 512, \JSON_THROW_ON_ERROR);

        return $data;
    }

    /**
     * @param array<string, PriceModifier> $modifiers
     *
     * @return list<array<string, mixed>>
     */
    private static function transformPriceModifications(array $modifiers): array
    {
        $rows = [];
        $priority = 0;

        foreach ($modifiers as $modifier) {
            $rows[] = [
                'id' => $modifier->getId() ?? Uuid::randomHex(),
                'price' => $modifier->getPrice()->getTotalPrice(), // already signed, no flip
                // price_definition is a plain JsonField, not a hydrated Struct -- needs explicit
                // jsonSerialize(); PriceModifierPriceDefinitionFactory::fromArray() is the inverse.
                'priceDefinition' => $modifier->getPriceDefinition()->jsonSerialize(),
                'label' => $modifier->getLabel(),
                'description' => $modifier->getDescription(),
                // tax_rules NULL means tax-exempt here, unlike getTaxRules()'s own NULL ("unrestricted") --
                // a taxable/unrestricted definition must persist as an empty collection, not NULL.
                'taxRules' => $modifier->getPriceDefinition()->isTaxExempt()
                    ? null
                    : ($modifier->getPriceDefinition()->getTaxRules() ?? new TaxRuleCollection()),
                'position' => $priority++,
                'type' => $modifier->getType(),
                'referencedId' => $modifier->getReferencedId(),
                'payload' => $modifier->getPayload(),
            ];
        }

        return $rows;
    }
}
