<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AgenticDiscovery\Discovery;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\AgenticDiscovery\DataAbstractionLayer\Definition\AgenticDiscoverySalesChannelConfigDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Invalidates the reverse-proxy cache for `/agents.md`, `/llms.txt`,
 * `/llms-full.txt` and `/sitemap_agentic_discovery.xml` whenever a merchant
 * updates the discovery configuration for the corresponding sales channel.
 *
 * The cache tag emitted by the controller is `agentic_discovery_{salesChannelId}`.
 * Both UPDATE and DELETE on the config entity invalidate that tag, so a
 * merchant who flips a toggle in the Administration sees the change reflected
 * on the next public request — without waiting out the
 * `s-maxage=3600` window.
 *
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_DISCOVERY
 *
 * @internal
 */
#[Package('framework')]
class AgenticDiscoveryCacheInvalidationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CacheInvalidator $cacheInvalidator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => 'invalidateOnConfigWrite',
        ];
    }

    public function invalidateOnConfigWrite(EntityWrittenContainerEvent $event): void
    {
        $primaryKeys = $event->getPrimaryKeysWithPropertyChange(
            AgenticDiscoverySalesChannelConfigDefinition::ENTITY_NAME,
            ['active', 'exposeAgentsMd', 'exposeLlmsTxt', 'exposeLlmsFullTxt', 'exposeAgenticSitemap', 'customIntro', 'customAgentRules', 'customSections']
        );

        if ($primaryKeys === []) {
            return;
        }

        $tags = [];
        foreach ($event->getEventByEntityName(AgenticDiscoverySalesChannelConfigDefinition::ENTITY_NAME)?->getPayloads() ?? [] as $payload) {
            if (isset($payload['salesChannelId']) && \is_string($payload['salesChannelId'])) {
                $tags[] = \sprintf('agentic_discovery_%s', $payload['salesChannelId']);
            }
        }

        if ($tags === []) {
            return;
        }

        $this->cacheInvalidator->invalidate(array_values(array_unique($tags)));
    }
}
