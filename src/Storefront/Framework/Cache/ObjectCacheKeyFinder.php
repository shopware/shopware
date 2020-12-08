<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache;

use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ObjectCacheKeyFinder
{
    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    /**
     * @var SalesChannelDefinitionInstanceRegistry
     */
    private $salesChannelDefinitionRegistry;

    /**
     * @var EntityCacheKeyGenerator
     */
    private $entityCacheKeyGenerator;

    /**
     * @var array
     */
    private $objects = [];

    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        EntityCacheKeyGenerator $entityCacheKeyGenerator,
        SalesChannelDefinitionInstanceRegistry $salesChannelDefinitionRegistry
    ) {
        $this->definitionRegistry = $definitionRegistry;
        $this->entityCacheKeyGenerator = $entityCacheKeyGenerator;
        $this->salesChannelDefinitionRegistry = $salesChannelDefinitionRegistry;
    }

    public function find(array $data, SalesChannelContext $context): array
    {
        $this->objects = [];

        $keys = $this->loop($data, $context);

        $contextKeys = $this->getObjectKeys($context, $context, false);

        foreach ($contextKeys as $key) {
            $keys[] = $key;
        }

        return array_filter(array_keys(array_flip($keys)));
    }

    private function loop($data, SalesChannelContext $context, bool $skipContext = true): array
    {
        $keys = [];
        foreach ($data as $item) {
            if (!$item instanceof Struct) {
                continue;
            }

            $nested = $this->getObjectKeys($item, $context, $skipContext);
            foreach ($nested as $key) {
                $keys[] = $key;
            }
        }

        return array_filter(array_keys(array_flip($keys)));
    }

    private function getObjectKeys(Struct $item, SalesChannelContext $context, bool $skipContext = true): array
    {
        if ($skipContext && $item instanceof SalesChannelContext) {
            return [];
        }

        $hash = spl_object_hash($item);

        // already iterated?
        if (isset($this->objects[$hash])) {
            return [];
        }

        $this->objects[$hash] = true;

        $keys = [];
        if ($item instanceof Collection) {
            return $this->loop($item, $context, $skipContext);
        }

        if ($item instanceof Entity) {
            $definition = $this->getByEntityClass($item);

            if (!$definition) {
                return [];
            }

            $keys[] = $this->entityCacheKeyGenerator->getEntityTag($item->getUniqueIdentifier(), $definition->getEntityName());
        }

        $data = $item->getVars();

        foreach ($data as $nestedItem) {
            if (!$nestedItem instanceof Struct) {
                continue;
            }

            $nested = $this->getObjectKeys($nestedItem, $context, $skipContext);

            foreach ($nested as $key) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    private function getByEntityClass(Entity $item): ?EntityDefinition
    {
        $definition = $this->definitionRegistry->getByEntityClass($item);

        if ($definition) {
            return $definition;
        }

        return $this->salesChannelDefinitionRegistry->getByEntityClass($item);
    }
}
