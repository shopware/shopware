<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField\ContentSystem\Mapping;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationDefinition;
use Shopware\Core\System\CustomField\CustomFieldDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @internal
 */
#[Package('framework')]
class CustomFieldMappingCandidateCacheInvalidator implements EventSubscriberInterface
{
    private const CACHE_TAG = 'custom_field_mapping_candidates';

    public function __construct(
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly TagAwareCacheInterface $cache,
    ) {
    }

    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [EntityWrittenContainerEvent::class => 'invalidate'];
    }

    public function invalidate(EntityWrittenContainerEvent $event): void
    {
        foreach ([
            CustomFieldDefinition::ENTITY_NAME,
            CustomFieldSetDefinition::ENTITY_NAME,
            CustomFieldSetRelationDefinition::ENTITY_NAME,
        ] as $entityName) {
            if ($event->getEventByEntityName($entityName) === null) {
                continue;
            }

            $this->cache->invalidateTags([self::CACHE_TAG]);
            $this->cacheInvalidator->invalidate(['custom_field']);

            return;
        }
    }
}
