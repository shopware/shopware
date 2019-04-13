<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Cache;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InvalidateCacheSubscriber implements EventSubscriberInterface
{
    /**
     * @var TagAwareAdapterInterface
     */
    private $cache;

    /**
     * @var EntityCacheKeyGenerator
     */
    private $cacheKeyGenerator;

    public function __construct(TagAwareAdapterInterface $cache, EntityCacheKeyGenerator $cacheKeyGenerator)
    {
        $this->cache = $cache;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
    }

    public static function getSubscribedEvents(): array
    {
        /*
         * Running this two times allows the indexers to use repositories.
         */
        return [
            EntityWrittenContainerEvent::NAME => [
                ['entitiesWrittenBefore', 20000],
                ['entitiesWrittenAfter', -20000],
            ],
        ];
    }

    /**
     * This method exists just to allow calling entitiesWritten two times in the event handler
     *
     * @internal
     */
    public function entitiesWrittenBefore(EntityWrittenContainerEvent $event): void
    {
        $this->entitiesWritten($event);
    }

    /**
     * This method exists just to allow calling entitiesWritten two times in the event handler
     *
     * @internal
     */
    public function entitiesWrittenAfter(EntityWrittenContainerEvent $event): void
    {
        $this->entitiesWritten($event);
    }

    public function entitiesWritten(EntityWrittenContainerEvent $event): void
    {
        $keys = [];

        $events = $event->getEvents();
        if (!$events) {
            return;
        }

        /** @var EntityWrittenEvent $writtenEvent */
        foreach ($events as $writtenEvent) {
            $definition = $writtenEvent->getDefinition();

            foreach ($writtenEvent->getWriteResults() as $result) {
                $id = $result->getPrimaryKey();

                if (\is_array($id)) {
                    $id = implode('-', $id);
                }

                $keys[] = $this->cacheKeyGenerator->getEntityTag($id, $writtenEvent->getDefinition());

                foreach ($result->getPayload() as $propertyName => $value) {
                    $field = $definition->getFields()->get($propertyName);

                    if ($field instanceof FkField) {
                        if ($value !== null) {
                            $keys[] = $field->getReferenceDefinition()->getEntityName() . '-' . $value;
                        }
                    }

                    if ($field instanceof TranslatedField) {
                        $field = EntityDefinitionQueryHelper::getTranslatedField($definition, $field);
                    }

                    if (!$field instanceof StorageAware) {
                        continue;
                    }
                    $keys[] = $writtenEvent->getDefinition()->getEntityName() . '.' . $field->getStorageName();
                }
            }
        }

        $keys = array_keys(array_flip($keys));
        $this->cache->invalidateTags($keys);
    }
}
