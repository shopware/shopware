<?php declare(strict_types=1);

namespace Shopware\Core\Framework\HealthCheck\Subscriber;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Shopware\Core\Framework\HealthCheck\Event\HealthCheckEvent;
use Shopware\Core\Framework\HealthCheck\Plugin\HealthCheckPluginInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class HealthCheckEventSubscriber implements EventSubscriberInterface
{

    /**
     * @var HealthCheckPluginInterface[]
     */
    private array $plugins = [];

    public static function getSubscribedEvents(): array
    {
        return [
            HealthCheckEvent::class => 'onHealthCheck',
        ];
    }

    /**
     * @param HealthCheckEvent $event
     *
     * @return HealthCheckEvent
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function onHealthCheck(HealthCheckEvent $event): HealthCheckEvent
    {
        foreach ($this->plugins as $plugin) {
            $event = $plugin->execute($event);
        }

        return $event;
    }

    public function addPlugin(HealthCheckPluginInterface $plugin): void
    {
        $this->plugins[] = $plugin;
    }
}
