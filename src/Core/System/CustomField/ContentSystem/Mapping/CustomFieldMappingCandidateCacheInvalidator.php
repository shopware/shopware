<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField\ContentSystem\Mapping;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
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
        $tags = [];
        $metadataChanged = false;

        foreach ([
            CustomFieldDefinition::ENTITY_NAME,
            CustomFieldSetDefinition::ENTITY_NAME,
            CustomFieldSetRelationDefinition::ENTITY_NAME,
        ] as $entityName) {
            $entityEvent = $event->getEventByEntityName($entityName);
            if ($entityEvent === null) {
                continue;
            }

            $metadataChanged = true;

            if ($entityName === CustomFieldDefinition::ENTITY_NAME) {
                foreach ($entityEvent->getIds() as $id) {
                    if (\is_string($id)) {
                        $tags[] = CustomFieldMappingCacheTags::candidate($this->normalizeId($id));
                    }
                }
            }

            if ($entityName === CustomFieldSetDefinition::ENTITY_NAME) {
                foreach ($entityEvent->getIds() as $id) {
                    if (\is_string($id)) {
                        $tags[] = CustomFieldMappingCacheTags::set($this->normalizeId($id));
                    }
                }
            }

            foreach ($entityEvent->getPayloads() as $payload) {
                $setId = $payload['customFieldSetId'] ?? $payload['setId'] ?? null;
                if (\is_string($setId)) {
                    $tags[] = CustomFieldMappingCacheTags::set($this->normalizeId($setId));
                }
            }
        }

        if (!$metadataChanged) {
            return;
        }

        $this->cache->invalidateTags([self::CACHE_TAG]);

        $this->cacheInvalidator->invalidate([CustomFieldMappingCacheTags::METADATA, ...array_values(array_unique($tags))]);
    }

    private function normalizeId(string $id): string
    {
        return \strlen($id) === 16 ? Uuid::fromBytesToHex($id) : $id;
    }
}
