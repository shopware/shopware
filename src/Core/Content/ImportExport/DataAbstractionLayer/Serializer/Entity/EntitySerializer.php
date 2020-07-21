<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Struct\Struct;

class EntitySerializer extends AbstractEntitySerializer
{
    public function serialize(Config $config, EntityDefinition $definition, $entity): iterable
    {
        if ($entity === null) {
            return;
        }

        if ($entity instanceof Struct) {
            $entity = $entity->jsonSerialize();
        }

        $fields = $definition->getFields();

        /* @var Field $field */
        foreach ($entity as $key => $value) {
            $field = $fields->get($key);
            if ($field === null) {
                yield $key => $value; // pass-through

                continue;
            }

            $serializer = $this->serializerRegistry->getFieldSerializer($field);
            yield from $serializer->serialize($config, $field, $value);
        }
    }

    public function deserialize(Config $config, EntityDefinition $definition, $record)
    {
        $fields = $definition->getFields();

        /* @var Field $field */
        foreach ($record as $key => $value) {
            $field = $fields->get($key);
            if ($field === null) {
                continue;
            }

            $serializer = $this->serializerRegistry->getFieldSerializer($field);
            $value = $serializer->deserialize($config, $field, $value);

            if ($value === null) {
                continue;
            }

            if (is_iterable($value) && !is_array($value)) {
                $value = iterator_to_array($value);
            }

            yield $key => $value;
        }
    }

    public function supports(string $entity): bool
    {
        return true;
    }
}
