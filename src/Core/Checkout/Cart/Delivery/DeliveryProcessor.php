<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class DeliveryProcessor implements CartProcessorInterface, CartDataCollectorInterface
{
    final public const MANUAL_SHIPPING_COSTS = 'manualShippingCosts';

    final public const SKIP_DELIVERY_PRICE_RECALCULATION = 'skipDeliveryPriceRecalculation';

    final public const SKIP_DELIVERY_TAX_RECALCULATION = 'skipDeliveryTaxRecalculation';

    /**
     * @var DeliveryBuilder
     */
    protected $builder;

    /**
     * @var DeliveryCalculator
     */
    protected $deliveryCalculator;

    /**
     * @var EntityRepository
     */
    protected $shippingMethodRepository;

    /**
     * @internal
     */
    public function __construct(
        DeliveryBuilder $builder,
        DeliveryCalculator $deliveryCalculator,
        EntityRepository $shippingMethodRepository
    ) {
        $this->builder = $builder;
        $this->deliveryCalculator = $deliveryCalculator;
        $this->shippingMethodRepository = $shippingMethodRepository;
    }

    public static function buildKey(string $shippingMethodId): string
    {
        return 'shipping-method-' . $shippingMethodId;
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        Profiler::trace('cart::delivery::collect', function () use ($data, $original, $context): void {
            $default = $context->getShippingMethod()->getId();

            if (!$data->has(self::buildKey($default))) {
                $ids = [$default];
            }

            foreach ($original->getDeliveries() as $delivery) {
                $id = $delivery->getShippingMethod()->getId();

                if (!$data->has(self::buildKey($id))) {
                    $ids[] = $id;
                }
            }

            if (empty($ids)) {
                return;
            }

            $criteria = new Criteria($ids);
            $criteria->addAssociation('prices');
            $criteria->addAssociation('deliveryTime');
            $criteria->addAssociation('tax');
            $criteria->setTitle('cart::shipping-methods');

            $shippingMethods = $this->shippingMethodRepository->search($criteria, $context->getContext());

            foreach ($ids as $id) {
                $key = self::buildKey($id);

                if (!$shippingMethods->has($id)) {
                    continue;
                }

                $data->set($key, $shippingMethods->get($id));
            }
        }, 'cart');
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        Profiler::trace('cart::delivery::process', function () use ($data, $original, $toCalculate, $context, $behavior): void {
            if ($behavior->hasPermission(self::SKIP_DELIVERY_PRICE_RECALCULATION)) {
                $deliveries = $original->getDeliveries();
                $firstDelivery = $deliveries->first();
                if ($firstDelivery === null) {
                    return;
                }

                // Stored original edit shipping cost
                $manualShippingCosts = $toCalculate->getExtension(self::MANUAL_SHIPPING_COSTS) ?? $firstDelivery->getShippingCosts();

                $toCalculate->addExtension(self::MANUAL_SHIPPING_COSTS, $manualShippingCosts);

                if ($manualShippingCosts instanceof CalculatedPrice) {
                    $firstDelivery->setShippingCosts($manualShippingCosts);
                }

                $this->deliveryCalculator->calculate($data, $toCalculate, $deliveries, $context);

                $toCalculate->setDeliveries($deliveries);

                return;
            }

            $deliveries = $this->builder->build($toCalculate, $data, $context, $behavior);
            $manualShippingCosts = $original->getExtension(self::MANUAL_SHIPPING_COSTS);

            if ($manualShippingCosts instanceof CalculatedPrice) {
                $deliveries->first()?->setShippingCosts($manualShippingCosts);
            }

            $this->deliveryCalculator->calculate($data, $toCalculate, $deliveries, $context);

            $toCalculate->setDeliveries($deliveries);
        }, 'cart');
    }
}
