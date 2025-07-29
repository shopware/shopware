<?php declare(strict_types=1);

namespace Shopware\Core\Service\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\Service\LifecycleManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class SystemUpdateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LifecycleManager $lifecycleManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UpdatePostFinishEvent::class => 'sync',
        ];
    }

    public function sync(UpdatePostFinishEvent $event): void
    {
        try {
            $this->lifecycleManager->sync($event->getContext());
        } catch (\Throwable $exception) {
            // this should not fail the update process, no matter what.
            $this->logger->error('Failed to sync lifecycle manager', ['exception' => $exception]);
        }
    }
}
