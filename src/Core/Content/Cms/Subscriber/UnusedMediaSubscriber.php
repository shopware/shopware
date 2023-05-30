<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore This would be useless as a unit test. It is integration tested here: \Shopware\Tests\Integration\Core\Content\Cms\Subscriber\UnusedMediaSubscriberTest
 */
#[Package('content')]
class UnusedMediaSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UnusedMediaSearchEvent::class => 'removeUsedMedia',
        ];
    }

    public function removeUsedMedia(UnusedMediaSearchEvent $event): void
    {
        $event->markAsUsed($this->findMediaIdsInImageGalleries($event));
        $event->markAsUsed($this->findMediaIdsInImages($event));

        foreach (['category_translation', 'product_translation'] as $table) {
            $event->markAsUsed($this->findMediaIdsInImageGalleriesInOverridesTable($table, $event));
            $event->markAsUsed($this->findMediaIdsInImagesInOverridesTable($table, $event));
        }
    }

    /**
     * @return array<string>
     */
    private function findMediaIdsInImageGalleriesInOverridesTable(string $table, UnusedMediaSearchEvent $event): array
    {
        $sql = <<<SQL
        SELECT JSON_EXTRACT(slot_config, "$.*.sliderItems.value[*].mediaId") as mediaId
        FROM $table
        WHERE JSON_OVERLAPS(
            JSON_EXTRACT(slot_config, "$.*.sliderItems.value[*].mediaId"),
            JSON_ARRAY(%s)
        );
        SQL;

        return $this->executeQueryWithIds($sql, $event);
    }

    /**
     * @return array<string>
     */
    private function findMediaIdsInImagesInOverridesTable(string $table, UnusedMediaSearchEvent $event): array
    {
        $sql = <<<SQL
        SELECT JSON_EXTRACT(slot_config, "$.*.media.value") as mediaId
        FROM $table
        WHERE JSON_OVERLAPS(
            JSON_EXTRACT(slot_config, "$.*.media.value"),
            JSON_ARRAY(%s)
        );
        SQL;

        return $this->executeQueryWithIds($sql, $event);
    }

    /**
     * @return array<string>
     */
    private function findMediaIdsInImageGalleries(UnusedMediaSearchEvent $event): array
    {
        $sql = <<<SQL
        SELECT JSON_EXTRACT(config, "$.sliderItems.value[*].mediaId") as mediaId
        FROM cms_slot_translation
        INNER JOIN cms_slot ON (cms_slot_translation.cms_slot_id = cms_slot.id)
        WHERE (cms_slot.type = 'image-slider' OR cms_slot.type = 'image-gallery')
        AND JSON_OVERLAPS(
            JSON_EXTRACT(config, "$.sliderItems.value[*].mediaId"),
            JSON_ARRAY(%s)
        );
        SQL;

        return $this->executeQueryWithIds($sql, $event);
    }

    /**
     * @return array<string>
     */
    private function findMediaIdsInImages(UnusedMediaSearchEvent $event): array
    {
        $sql = <<<SQL
        SELECT JSON_ARRAY(JSON_EXTRACT(config, "$.media.value")) as mediaId
        FROM cms_slot_translation
        INNER JOIN cms_slot ON (cms_slot_translation.cms_slot_id = cms_slot.id)
        WHERE cms_slot.type = 'image'
        AND JSON_OVERLAPS(
            JSON_EXTRACT(config, "$.media.value"),
            JSON_ARRAY(%s)
        );
        SQL;

        return $this->executeQueryWithIds($sql, $event);
    }

    /**
     * @return array<string>
     */
    private function executeQueryWithIds(string $sql, UnusedMediaSearchEvent $event): array
    {
        $result = $this->connection->fetchFirstColumn(
            sprintf($sql, implode(',', array_map(fn (string $id) => sprintf('"%s"', $id), $event->getUnusedIds())))
        );

        //json_decode each row and flatten the result to an array of ids
        return array_merge(
            ...array_map(fn (string $ids) => json_decode($ids, true, \JSON_THROW_ON_ERROR), $result)
        );
    }
}
