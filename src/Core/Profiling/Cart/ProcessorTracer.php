<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Cart;

use Shopware\Core\Checkout\CustomerContext;
use Shopware\Core\Checkout\Cart\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Core\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\LineItem\CalculatedLineItemInterface;
use Shopware\Core\Framework\Struct\StructCollection;

class ProcessorTracer implements CartProcessorInterface
{
    /**
     * @var CartProcessorInterface
     */
    private $decorated;

    /**
     * @var TracedCartActions
     */
    private $actions;

    public function __construct(
        CartProcessorInterface $decorated,
        TracedCartActions $actions
    ) {
        $this->decorated = $decorated;
        $this->actions = $actions;
    }

    public function process(
        Cart $cart,
        CalculatedCart $calculatedCart,
        StructCollection $dataCollection,
        CustomerContext $context
    ): void {
        $before = clone $calculatedCart;

        $this->decorated->process($cart, $calculatedCart, $dataCollection, $context);

        $lineItems = $before->getCalculatedLineItems();
        $deliveries = $before->getDeliveries();

        $class = get_class($this->decorated);

        /** @var CalculatedLineItemInterface $lineItem */
        foreach ($calculatedCart->getCalculatedLineItems() as $lineItem) {
            if (!$lineItems->has($lineItem->getIdentifier())) {
                $this->actions->actions[$class][] = [
                    'action' => sprintf(
                        'Added %s: %s',
                        $this->getClassName($lineItem),
                        $lineItem->getIdentifier()
                    ),
                    'before' => null,
                    'after' => null,
                    'item' => $lineItem,
                ];
            }
        }

        /** @var Delivery $delivery */
        foreach ($calculatedCart->getDeliveries() as $delivery) {
            $exists = $deliveries->getDelivery(
                $delivery->getDeliveryDate(),
                $delivery->getLocation()
            );

            if (!$exists) {
                $this->actions->actions[$class][] = [
                    'action' => sprintf(
                        'Added delivery %s and %s to location %s',
                        $delivery->getDeliveryDate()->getEarliest()->format('Y-m-d'),
                        $delivery->getDeliveryDate()->getLatest()->format('Y-m-d'),
                        $delivery->getLocation()->getCountry()->getName()
                    ),
                    'before' => null,
                    'after' => null,
                    'item' => $delivery,
                ];
                continue;
            }

            if ($exists->getShippingCosts() !== $delivery->getShippingCosts()) {
                $this->actions->actions[$class][] = [
                    'action' => 'calculated shipping costs',
                    'before' => $exists->getShippingCosts(),
                    'after' => $delivery->getShippingCosts(),
                    'item' => $delivery,
                ];
            }

            $positions = $exists->getPositions();
            /** @var DeliveryPosition $position */
            foreach ($delivery->getPositions() as $position) {
                $existingPosition = $positions->get($position->getIdentifier());

                if (!$existingPosition) {
                    $this->actions->actions[$class][] = [
                        'action' => 'added delivery position',
                        'before' => null,
                        'after' => null,
                        'item' => $position,
                    ];
                    continue;
                }

                if ($existingPosition->getQuantity() !== $position->getQuantity()) {
                    $this->actions->actions[$class][] = [
                        'action' => 'changed delivery position quantity',
                        'item' => $position,
                        'before' => $existingPosition->getQuantity(),
                        'after' => $position->getQuantity(),
                    ];
                    continue;
                }
            }
        }
    }

    private function getClassName($instance)
    {
        $name = get_class($instance);
        $names = explode('\\', $name);

        return array_pop($names);
    }
}
