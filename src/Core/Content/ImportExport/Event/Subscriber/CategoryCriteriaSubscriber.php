<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event\Subscriber;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\ImportExport\Event\EnrichExportCriteriaEvent;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('system-settings')]
class CategoryCriteriaSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EnrichExportCriteriaEvent::class => 'enrich',
        ];
    }

    public function enrich(EnrichExportCriteriaEvent $event): void
    {
        /** @var ImportExportProfileEntity $profile */
        $profile = $event->getLogEntity()->getProfile();
        if ($profile->getSourceEntity() !== CategoryDefinition::ENTITY_NAME) {
            return;
        }

        $criteria = $event->getCriteria();
        $criteria->resetSorting();

        $criteria->addSorting(new FieldSorting('level'));
        $criteria->addSorting(new FieldSorting('id'));
    }
}
