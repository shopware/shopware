<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('core')]
class EntitySerializer extends AbstractEntitySerializer
{
    /**
     * @param array<mixed>|Struct|null $entity
     *
     * @return \Generator
     */
    public function serialize(Config $config, EntityDefinition $definition, $entity): iterable
    {
        if ($entity === null) {
            return;
        }

        if ($entity instanceof Struct) {
            $entity = $entity->jsonSerialize();
        }

        $fields = $definition->getFields();
        $extensionFields = $definition->getExtensionFields();

        foreach ($entity as $key => $value) {
            if ($key === 'extensions') {
                foreach ($value as $extensionKey => $extensionValue) {
                    if (isset($extensionFields[$extensionKey])) {
                        $serializer = $this->serializerRegistry->getFieldSerializer($extensionFields[$extensionKey]);
                        yield from $serializer->serialize($config, $extensionFields[$extensionKey], $extensionValue);
                    }
                }

                continue;
            }

            $field = $fields->get($key);
            if ($field === null) {
                yield $key => $value; // pass-through

                continue;
            }

            $serializer = $this->serializerRegistry->getFieldSerializer($field);
            yield from $serializer->serialize($config, $field, $value);
        }
    }

    /**
     * @param array<mixed>|\Traversable<mixed> $entity
     *
     * @return array<mixed>|\Traversable<mixed>
     */
    public function deserialize(Config $config, EntityDefinition $definition, $entity)
    {
        $entity = \is_array($entity) ? $entity : iterator_to_array($entity);
        $fields = $definition->getFields();

        foreach ($entity as $key => $value) {
            if ($key === '_error' && $value instanceof \Throwable) {
                yield '_error' => $value;
            }

            $field = $fields->get($key);
            if ($field === null) {
                continue;
            }

            $serializer = $this->serializerRegistry->getFieldSerializer($field);
            $value = $serializer->deserialize($config, $field, $value);

            if ($value === null) {
                continue;
            }

            if (is_iterable($value) && !\is_array($value)) {
                $value = iterator_to_array($value);
            }

            // ToOneSerializer may return records with errors
            if (\is_array($value) && isset($value['_error']) && $value['_error'] instanceof \Throwable) {
                yield '_error' => $value['_error'];
            }

            yield $key => $value;
        }
    }

    public function supports(string $entity): bool
    {
        return true;
    }
}
