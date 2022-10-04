<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - EventSubscribers will become internal in v6.5.0
 */
class PluginLoadedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PluginEvents::PLUGIN_LOADED_EVENT => [
                ['unserialize'],
            ],
        ];
    }

    public function unserialize(EntityLoadedEvent $event): void
    {
        /** @var PluginEntity $plugin */
        foreach ($event->getEntities() as $plugin) {
            if ($plugin->getIconRaw()) {
                $plugin->setIcon(base64_encode($plugin->getIconRaw()));
            }
        }
    }
}
