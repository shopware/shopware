<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field;

use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;

abstract class AbstractFieldSerializer
{
    /**
     * @var SerializerRegistry
     */
    protected $serializerRegistry;

    abstract public function serialize(Field $field, $value): iterable;

    abstract public function deserialize(Field $field, $value);

    abstract public function supports(Field $field): bool;

    public function setRegistry(SerializerRegistry $serializerRegistry): void
    {
        $this->serializerRegistry = $serializerRegistry;
    }
}
