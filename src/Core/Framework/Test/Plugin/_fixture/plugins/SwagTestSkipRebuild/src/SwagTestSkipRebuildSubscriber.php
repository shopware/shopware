<?php declare(strict_types=1);

namespace SwagTestSkipRebuild;

use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreDeactivateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - EventSubscribers will become internal in v6.5.0
 */
class SwagTestSkipRebuildSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            PluginPreActivateEvent::class => 'preActivate',
            PluginPostActivateEvent::class => 'postActivate',
            PluginPreDeactivateEvent::class => 'preDeactivate',
            PluginPostDeactivateEvent::class => 'postDeactivate',
        ];
    }

    public function preActivate(PluginPreActivateEvent $event): void
    {
        $plugin = $event->getContext()->getPlugin();
        if (!($plugin instanceof SwagTestSkipRebuild)) {
            return;
        }

        $plugin->preActivateContext = $event->getContext();
    }

    public function postActivate(PluginPostActivateEvent $event): void
    {
        $plugin = $event->getContext()->getPlugin();
        if (!($plugin instanceof SwagTestSkipRebuild)) {
            return;
        }

        $plugin->postActivateContext = $event->getContext();
    }

    public function preDeactivate(PluginPreDeactivateEvent $event): void
    {
        $plugin = $event->getContext()->getPlugin();
        if (!($plugin instanceof SwagTestSkipRebuild)) {
            return;
        }

        $plugin->preDeactivateContext = $event->getContext();
    }

    public function postDeactivate(PluginPostDeactivateEvent $event): void
    {
        $plugin = $event->getContext()->getPlugin();
        if (!($plugin instanceof SwagTestSkipRebuild)) {
            return;
        }

        $plugin->postDeactivateContext = $event->getContext();
    }
}
