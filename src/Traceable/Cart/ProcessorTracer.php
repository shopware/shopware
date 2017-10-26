<?php declare(strict_types=1);

namespace Shopware\Traceable\Cart;

use Shopware\Cart\Cart\CartContainer;
use Shopware\Cart\Cart\CartProcessorInterface;
use Shopware\Cart\Cart\ProcessorCart;
use Shopware\Cart\Delivery\Delivery;
use Shopware\Cart\Delivery\DeliveryPosition;
use Shopware\Cart\LineItem\CalculatedLineItemInterface;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\StructCollection;

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
        CartContainer $cartContainer,
        ProcessorCart $processorCart,
        StructCollection $dataCollection,
        ShopContext $context
    ): void {
        $before = clone $processorCart;

        $this->decorated->process($cartContainer, $processorCart, $dataCollection, $context);

        $lineItems = $before->getCalculatedLineItems();
        $deliveries = $before->getDeliveries();

        $class = get_class($this->decorated);

        /** @var CalculatedLineItemInterface $lineItem */
        foreach ($processorCart->getCalculatedLineItems() as $lineItem) {
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
        foreach ($processorCart->getDeliveries() as $delivery) {
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
