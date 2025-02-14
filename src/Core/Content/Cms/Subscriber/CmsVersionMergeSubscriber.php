<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeVersionMergeEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * Handles cleanup of CMS entities (`cms_slot`) referencing deleted parent entities (`cms_block`) during a version merge.
 * This solution addresses a specific corner case for CMS entities. If a similar issue arises with other entities,
 * consider generalizing this approach.
 */
#[Package('discovery')]
class CmsVersionMergeSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            BeforeVersionMergeEvent::class => 'onBeforeVersionMerge',
        ];
    }

    public function onBeforeVersionMerge(BeforeVersionMergeEvent $event): void
    {
        $writes = $event->writes;

        if (empty($writes['delete']['cms_block'])) {
            return;
        }

        $deletedBlocks = $this->mapDeletedBlocks($writes['delete']['cms_block']);

        // Filter slots based on deleted blocks
        foreach (['insert', 'update'] as $operation) {
            if (empty($writes[$operation]['cms_slot'])) {
                continue;
            }

            $writes[$operation]['cms_slot'] = $this->filterSlots($writes[$operation]['cms_slot'], $deletedBlocks);
        }

        $event->writes = $writes;
    }

    /**
     * @param array<int, array<string, mixed>> $deletedBlocks
     *
     * @return array<string, array<string, bool>>
     */
    private function mapDeletedBlocks(array $deletedBlocks): array
    {
        $mapped = [];
        foreach ($deletedBlocks as $deletedBlock) {
            $blockId = isset($deletedBlock['id']) ? (string) $deletedBlock['id'] : null;
            $blockVersionId = isset($deletedBlock['versionId']) ? (string) $deletedBlock['versionId'] : null;

            if ($blockId && $blockVersionId) {
                $mapped[$blockId][$blockVersionId] = true;
            }
        }

        return $mapped;
    }

    /**
     * @param array<int, array<string, mixed>> $slots
     * @param array<string, array<string, bool>> $deletedBlocks
     *
     * @return array<int, array<string, mixed>>
     */
    private function filterSlots(array $slots, array $deletedBlocks): array
    {
        return array_values(array_filter(
            $slots,
            static function (array $slot) use ($deletedBlocks): bool {
                $blockId = $slot['blockId'] ?? null;
                $blockVersionId = $slot['cmsBlockVersionId'] ?? null;

                return empty($deletedBlocks[$blockId][$blockVersionId]);
            }
        ));
    }
}
