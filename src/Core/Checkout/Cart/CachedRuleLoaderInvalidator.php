<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Content\Rule\Event\RuleIndexerEvent;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationLogger;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CachedRuleLoaderInvalidator implements EventSubscriberInterface
{
    private CacheInvalidationLogger $logger;

    public function __construct(CacheInvalidationLogger $logger)
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [
            RuleIndexerEvent::class => 'invalidate',
            PluginPostInstallEvent::class => 'invalidate',
            PluginPostActivateEvent::class => 'invalidate',
            PluginPostUpdateEvent::class => 'invalidate',
            PluginPostDeactivateEvent::class => 'invalidate',
            PluginPostUninstallEvent::class => 'invalidate',
        ];
    }

    public function invalidate(): void
    {
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return;
        }
        $this->logger->log([CachedRuleLoader::CACHE_KEY]);
    }
}
