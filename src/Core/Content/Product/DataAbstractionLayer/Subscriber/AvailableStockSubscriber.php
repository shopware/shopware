<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\Subscriber;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\Stock\AvailableStockCalculatorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AvailableStockSubscriber implements EventSubscriberInterface
{
    /** @var AvailableStockCalculatorInterface */
    private $stockCalculator;

    public function __construct(AvailableStockCalculatorInterface $stockCalculator)
    {
        $this->stockCalculator = $stockCalculator;
    }

    public static function getSubscribedEvents()
    {
        return [
            EntityWrittenContainerEvent::NAME => [
                ['onWriteEntity', 500]
            ]
        ];
    }

    public function onWriteEntity(EntityWrittenContainerEvent $event)
    {
        /** @var EntityWrittenEvent $productWrittenEvent */
        $productWrittenEvent = $event->getEventByDefinition(ProductDefinition::class);

        if (null === $productWrittenEvent) {
            return;
        }

        foreach ($productWrittenEvent->getWriteResults() as $result) {
            $this->handleWriteResult($result);
        }
    }

    private function handleWriteResult(EntityWriteResult $result)
   {
        $this->stockCalculator->calculate(
            $result->getPrimaryKey(),
            $result->getPayload()['stock']
        );
    }
}