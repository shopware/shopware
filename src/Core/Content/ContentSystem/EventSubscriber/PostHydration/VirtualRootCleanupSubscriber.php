<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\EventSubscriber\PostHydration;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Event\AfterContentHydrationEvent;
use Shopware\Core\Content\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Removes virtual root wrapper added by VirtualRootPreparationSubscriber.
 *
 * Restores original layout structure after hydration completes.
 *
 * @internal
 */
#[Package('discovery')]
class VirtualRootCleanupSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly VirtualRootWrapper $virtualRootWrapper
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterContentHydrationEvent::class => ['onAfterContentHydration', 1000],
        ];
    }

    public function onAfterContentHydration(AfterContentHydrationEvent $event): void
    {
        if (!$this->virtualRootWrapper->requiresWrapping($event->specification, $event->elements)) {
            return;
        }

        if (\count($event->elements) !== 1) {
            throw ContentSystemException::pathIntegrityViolation(
                \sprintf(
                    'Expected exactly 1 virtual root after preparation, found %d roots. This indicates a preparation integrity violation.',
                    \count($event->elements)
                )
            );
        }

        $event->elements = $this->virtualRootWrapper->unwrap($event->elements[0]);
    }
}
