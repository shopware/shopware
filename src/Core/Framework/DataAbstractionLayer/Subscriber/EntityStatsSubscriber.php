<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Histogram;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('core')]
class EntityStatsSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly Meter $meter)
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            EntitySearchedEvent::class => ['onEntitySearched', 99],
        ];
    }

    public function onEntitySearched(EntitySearchedEvent $event): void
    {
        $criteria = $event->getCriteria();
        $associationsCount = $this->getAssociationsCountFromCriteria($criteria);
        $this->meter->emit(new Histogram(
            name: 'dal.association.count',
            value: $associationsCount,
            description: 'Number of associations in request',
        ));
    }

    private function getAssociationsCountFromCriteria(Criteria $criteria): int
    {
        return array_reduce(
            $criteria->getAssociations(),
            fn (int $carry, Criteria $association) => $carry + 1 + $this->getAssociationsCountFromCriteria($association),
            0
        );
    }
}
