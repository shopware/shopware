<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event\Subscriber;

use Shopware\Core\Content\ImportExport\Event\EnrichExportCriteriaEvent;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductCriteriaSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            EnrichExportCriteriaEvent::class => 'enrich',
        ];
    }

    public function enrich(EnrichExportCriteriaEvent $event): void
    {
        if ($event->getLogEntity()->getProfile()->getSourceEntity() !== ProductDefinition::ENTITY_NAME) {
            return;
        }

        $criteria = $event->getCriteria();
        $criteria->resetSorting();

        $criteria->addSorting(new FieldSorting('autoIncrement'));

        $config = Config::fromLog($event->getLogEntity());

        if ($config->get('includeVariants') !== true) {
            $criteria->addFilter(new EqualsFilter('parentId', null));
        }
    }
}
