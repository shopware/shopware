<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Event\RefreshIndexEvent;
use Shopware\Elasticsearch\Admin\AdminIndexingBehavior;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @package system-settings
 *
 * @internal
 */
final class RefreshIndexSubscriber implements EventSubscriberInterface
{
    private AdminSearchRegistry $registry;

    public function __construct(AdminSearchRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents()
    {
        return [
            RefreshIndexEvent::class => 'handled',
        ];
    }

    public function handled(RefreshIndexEvent $event): void
    {
        $this->registry->iterate(new AdminIndexingBehavior($event->getUseQueue()));
    }
}
