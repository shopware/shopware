<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Cache;

use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InvalidateCacheSubscriber implements EventSubscriberInterface
{
    /**
     * @var CacheClearer
     */
    private $cache;

    /**
     * @var EntityCacheKeyGenerator
     */
    private $cacheKeyGenerator;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    public function __construct(
        CacheClearer $cache,
        EntityCacheKeyGenerator $cacheKeyGenerator,
        DefinitionInstanceRegistry $definitionRegistry
    ) {
        $this->cache = $cache;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->definitionRegistry = $definitionRegistry;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => [
                ['entitiesWritten', -20000],
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
            $definition = $this->definitionRegistry->getByEntityName($writtenEvent->getEntityName());

            foreach ($writtenEvent->getWriteResults() as $result) {
                $id = $result->getPrimaryKey();

                if (\is_array($id)) {
                    $id = implode('-', $id);
                }

                $keys[] = $this->cacheKeyGenerator->getEntityTag($id, $definition->getEntityName());

                foreach ($result->getPayload() as $propertyName => $value) {
                    $field = $definition->getFields()->get($propertyName);

                    if (($field instanceof FkField) && $value !== null) {
                        $keys[] = $this->cacheKeyGenerator->getEntityTag($value, $field->getReferenceDefinition()->getEntityName());
                    }

                    if ($field instanceof TranslatedField) {
                        $field = EntityDefinitionQueryHelper::getTranslatedField($definition, $field);
                    }

                    if (!$field instanceof StorageAware) {
                        continue;
                    }
                    $keys[] = $this->cacheKeyGenerator->getFieldTag($definition, $field->getStorageName());
                }
            }
        }

        $keys = array_filter(array_keys(array_flip($keys)));
        $this->cache->invalidateTags($keys);
    }
}
