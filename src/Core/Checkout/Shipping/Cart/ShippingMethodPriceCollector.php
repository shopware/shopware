<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CollectorInterface;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Struct\StructCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ShippingMethodPriceCollector implements CollectorInterface
{
    public const DATA_KEY = 'shipping-method-price';

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    public function __construct(EntityRepositoryInterface $shippingMethodPriceRepository)
    {
        $this->repository = $shippingMethodPriceRepository;
    }

    public function prepare(
        StructCollection $definitions,
        Cart $cart,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        $ruleIds = $context->getRuleIds();

        if (!$ruleIds) {
            return;
        }

        $shippingMethodsIds = [];

        if (!$behavior->isRecalculation()) {
            $shippingMethodsIds[] = $context->getShippingMethod()->getId();

            // remove invalid prices from context
            $this->removeInvalidPrices($context->getShippingMethod()->getPrices(), $ruleIds);
        }

        //remove prices which are in cart but the rule id is not in provided context
        foreach ($cart->getDeliveries() as $delivery) {
            $shippingMethodsIds[] = $delivery->getShippingMethod()->getId();
            $this->removeInvalidPrices($delivery->getShippingMethod()->getPrices(), $ruleIds);
        }

        if (!$shippingMethodsIds) {
            return;
        }

        $definitions->set(self::DATA_KEY, new ShippingMethodPriceFetchDefinition($ruleIds, $shippingMethodsIds));
    }

    public function collect(
        StructCollection $fetchDefinitions,
        StructCollection $data,
        Cart $cart,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        $priceDefinitions = $fetchDefinitions->filterInstance(ShippingMethodPriceFetchDefinition::class);

        if ($priceDefinitions->count() === 0) {
            return;
        }

        $ids = [];
        // also load context shipping method prices
        $shippingMethodIds = [$context->getShippingMethod()->getId()];

        /** @var ShippingMethodPriceFetchDefinition[] $priceDefinitions */
        foreach ($priceDefinitions as $definition) {
            array_push($ids, ...$definition->getRuleIds());
            array_push($shippingMethodIds, ...$definition->getShippingMethodIds());
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_OR, [
                    new EqualsFilter('ruleId', null),
                    new EqualsAnyFilter('ruleId', $ids),
                ]
            )
        );
        $criteria->addFilter(new EqualsAnyFilter('shippingMethodId', $shippingMethodIds));
        $prices = $this->repository->search($criteria, $context->getContext());

        $data->set(self::DATA_KEY, $prices);
    }

    public function enrich(
        StructCollection $data,
        Cart $cart,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        if (!$data->has(self::DATA_KEY)) {
            return;
        }

        /** @var ShippingMethodPriceCollection $prices */
        $prices = $data->get(self::DATA_KEY);

        foreach ($prices as $price) {
            foreach ($cart->getDeliveries() as $delivery) {
                if ($price->getShippingMethodId() === $delivery->getShippingMethod()->getId()) {
                    $delivery->getShippingMethod()->getPrices()->add($price);
                }
            }

            if ($behavior->isRecalculation()) {
                continue;
            }

            // add prices to context
            if ($context->getShippingMethod()->getId() === $price->getShippingMethodId()) {
                $context->getShippingMethod()->getPrices()->add($price);
            }
        }
    }

    /**
     * @param string[] $ruleIds
     */
    private function removeInvalidPrices(ShippingMethodPriceCollection $prices, array $ruleIds): void
    {
        foreach ($prices as $index => $price) {
            if (\in_array($price->getRuleId(), $ruleIds, true)) {
                continue;
            }

            $prices->remove($index);
        }
    }
}
