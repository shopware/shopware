<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport;

use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Reset all serializer cache properties before each test to prevent side effects.
 * A property is noticed as a cache if it is of type array and has 'cache' in it's name.
 *
 * A serializer is any service tagged with
 * 'shopware.import_export.entity_serializer'
 * or
 * 'shopware.import_export.field_serializer'
 */
trait SerializerCacheTestBehaviour
{
    /**
     * @before
     */
    public function clearSerializerCacheData(): void
    {
        /** @var SerializerRegistry $serializerRegistry */
        $serializerRegistry = $this->getContainer()
            ->get('test.service_container')
            ->get(SerializerRegistry::class);

        foreach ($serializerRegistry->getAllEntitySerializers() as $serializer) {
            $this->resetInternalSerializerCache(\get_class($serializer));
        }

        foreach ($serializerRegistry->getAllFieldSerializers() as $serializer) {
            $this->resetInternalSerializerCache(\get_class($serializer));
        }
    }

    abstract protected function getContainer(): ContainerInterface;

    private function resetInternalSerializerCache(string $class): void
    {
        $reflectionClass = new \ReflectionClass($class);
        $properties = $reflectionClass->getProperties();

        foreach ($properties as $property) {
            $type = $property->getType();

            // type check: it must be an array to be reset
            if (
                $type === null
                || !$type->isBuiltin()
                || !($type instanceof \ReflectionNamedType)
                || $type->getName() !== 'array'
            ) {
                continue;
            }

            // every cache property needs to have 'cache' in it's name to be reset
            if (mb_strpos($property->getName(), 'cache') === false) {
                continue;
            }

            $property->setAccessible(true);
            $property->setValue($this->getContainer()->get($class), []);
        }
    }
}
