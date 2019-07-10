<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DeliveryProcessor implements CartProcessorInterface, CartDataCollectorInterface
{
    /**
     * @var DeliveryBuilder
     */
    protected $builder;

    /**
     * @var DeliveryCalculator
     */
    protected $deliveryCalculator;

    /**
     * @var EntityRepositoryInterface
     */
    protected $shippingMethodRepository;

    public function __construct(
        DeliveryBuilder $builder,
        DeliveryCalculator $deliveryCalculator,
        EntityRepositoryInterface $shippingMethodRepository
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

        $shippingMethods = $this->shippingMethodRepository->search($criteria, $context->getContext());

        foreach ($ids as $id) {
            $key = self::buildKey($id);

            if (!$shippingMethods->has($id)) {
                continue;
            }

            $data->set($key, $shippingMethods->get($id));
        }
    }

    public function process(CartDataCollection $data, Cart $original, Cart $calculated, SalesChannelContext $context, CartBehavior $behavior): void
    {
        if ($behavior->isRecalculation()) {
            $deliveries = $original->getDeliveries();

            $this->deliveryCalculator->calculate($data, $calculated, $deliveries, $context);

            $calculated->setDeliveries($deliveries);

            return;
        }

        $deliveries = $this->builder->build($calculated, $data, $context, $behavior);

        $this->deliveryCalculator->calculate($data, $calculated, $deliveries, $context);

        $calculated->setDeliveries($deliveries);
    }
}
