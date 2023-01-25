<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Event\RefreshIndexEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Admin\AdminIndexingBehavior;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('system-settings')]
final class RefreshIndexSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly AdminSearchRegistry $registry)
    {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            RefreshIndexEvent::class => 'handled',
        ];
    }

    public function handled(RefreshIndexEvent $event): void
    {
        $this->registry->iterate(
            new AdminIndexingBehavior(
                $event->getNoQueue(),
                $event->getSkipEntities(),
                $event->getOnlyEntities()
            )
        );
    }
}
