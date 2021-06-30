<?php declare(strict_types=1);

namespace SwagTestSkipRebuild;

use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreDeactivateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

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
        if (!\get_class($plugin) === 'SwagTestSkipRebuild\\SwagTestSkipRebuild') {
            return;
        }

        $plugin->preActivateContext = $event->getContext();
    }

    public function postActivate(PluginPostActivateEvent $event): void
    {
        $plugin = $event->getContext()->getPlugin();
        if (!\get_class($plugin) === 'SwagTestSkipRebuild\\SwagTestSkipRebuild') {
            return;
        }

        $plugin->postActivateContext = $event->getContext();
    }

    public function preDeactivate(PluginPreDeactivateEvent $event): void
    {
        $plugin = $event->getContext()->getPlugin();
        if (!\get_class($plugin) === 'SwagTestSkipRebuild\\SwagTestSkipRebuild') {
            return;
        }

        $plugin->preDeactivateContext = $event->getContext();
    }

    public function postDeactivate(PluginPostDeactivateEvent $event): void
    {
        $plugin = $event->getContext()->getPlugin();
        if (!\get_class($plugin) === 'SwagTestSkipRebuild\\SwagTestSkipRebuild') {
            return;
        }

        $plugin->postDeactivateContext = $event->getContext();
    }
}
