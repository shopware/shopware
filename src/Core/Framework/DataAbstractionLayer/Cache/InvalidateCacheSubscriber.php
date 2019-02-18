<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Cache;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\StorageAware;
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
        return [
            EntityWrittenContainerEvent::NAME => [
                ['entitiesWritten', 1000],
            ],
        ];
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
                    $field = $definition::getFields()->get($propertyName);

                    if ($field instanceof TranslatedField) {
                        $field = EntityDefinitionQueryHelper::getTranslatedField($definition, $field);
                    }

                    if (!$field instanceof StorageAware) {
                        continue;
                    }
                    $keys[] = $writtenEvent->getDefinition()::getEntityName() . '.' . $field->getStorageName();
                }
            }
        }

        $keys = array_keys(array_flip($keys));
        $this->cache->invalidateTags($keys);
    }
}
