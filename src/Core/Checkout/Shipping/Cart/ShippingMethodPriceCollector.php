<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CollectorInterface;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
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
        // nth
    }

    public function collect(
        StructCollection $fetchDefinitions,
        StructCollection $data,
        Cart $cart,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        // nth
    }

    public function enrich(
        StructCollection $data,
        Cart $cart,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        if ($behavior->isRecalculation()) {
            return;
        }

        $shippingMethodIds = [$context->getShippingMethod()->getId()];

        $context->getShippingMethod()->setPrices(new ShippingMethodPriceCollection());

        foreach ($cart->getDeliveries() as $delivery) {
            $shippingMethodIds[] = $delivery->getShippingMethod()->getId();
            $delivery->getShippingMethod()->setPrices(new ShippingMethodPriceCollection());
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('shippingMethodId', $shippingMethodIds));
        $prices = $this->repository->search($criteria, $context->getContext());

        foreach ($prices as $price) {
            foreach ($cart->getDeliveries() as $delivery) {
                if ($price->getShippingMethodId() === $delivery->getShippingMethod()->getId()) {
                    $delivery->getShippingMethod()->getPrices()->add($price);
                }
            }

            // add prices to context
            if ($context->getShippingMethod()->getId() === $price->getShippingMethodId()) {
                $context->getShippingMethod()->getPrices()->add($price);
            }
        }
    }
}
