<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeVersionMergeEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('buyers-experience')]
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
        $writes = &$event->getWrites();

        if (empty($writes['delete']['cms_block'])) {
            return;
        }

        $deletedBlocks = [];
        foreach ($writes['delete']['cms_block'] as $deletedBlock) {
            $blockId = $deletedBlock['id'] ?? null;
            $blockVersionId = $deletedBlock['versionId'] ?? null;

            if ($blockId && $blockVersionId) {
                $deletedBlocks[$blockId][$blockVersionId] = true;
            }
        }

        foreach (['insert', 'update'] as $operation) {
            if (!empty($writes[$operation]['cms_slot'])) {
                $writes[$operation]['cms_slot'] = array_values(array_filter(
                    $writes[$operation]['cms_slot'],
                    function ($slot) use ($deletedBlocks) {
                        $blockId = $slot['blockId'] ?? null;
                        $blockVersionId = $slot['cmsBlockVersionId'] ?? null;

                        return empty($deletedBlocks[$blockId][$blockVersionId]);
                    }
                ));
            }
        }
    }
}
