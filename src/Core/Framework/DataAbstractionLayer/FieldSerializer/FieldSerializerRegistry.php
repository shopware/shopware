<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\MissingFieldSerializerException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

class FieldSerializerRegistry
{
    /**
     * @var FieldSerializerInterface[]
     */
    protected $serializers = [];

    /**
     * @var FieldSerializerInterface[]
     */
    protected $mapped;

    public function __construct(iterable $serializers)
    {
        $this->serializers = $serializers;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        yield from $this->getSerializer($field)->encode($field, $existence, $data, $parameters);
    }

    public function decode(Field $field, $value)
    {
        return $this->getSerializer($field)->decode($field, $value);
    }

    /**
     * @return FieldSerializerInterface[]
     */
    private function getSerializers(): array
    {
        if ($this->mapped === null) {
            $this->mapped = [];

            /** @var FieldSerializerInterface $serializer */
            foreach ($this->serializers as $serializer) {
                $this->mapped[$serializer->getFieldClass()] = $serializer;
            }
        }

        return $this->mapped;
    }

    private function getSerializer(Field $field): FieldSerializerInterface
    {
        $class = get_class($field);

        $serializers = $this->getSerializers();
        $serializer = $serializers[$class] ?? null;

        if (!$serializer) {
            $serializer = $this->findInheritSerializer($field);

            if (!$serializer) {
                throw new MissingFieldSerializerException($field);
            }
        }

        return $serializer;
    }

    private function findInheritSerializer(Field $field): ?FieldSerializerInterface
    {
        $serializers = $this->getSerializers();

        foreach ($serializers as $serializer) {
            if (is_subclass_of($field, $serializer->getFieldClass())) {
                return $this->mapped[get_class($field)] = $serializer;
            }
        }

        return null;
    }
}
