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
        $class = get_class($field);

        $serializers = $this->getSerializers();

        if (!isset($serializers[$class])) {
            throw new MissingFieldSerializerException($field);
        }

        $serializer = $serializers[$class];

        yield from $serializer->encode($field, $existence, $data, $parameters);
    }

    public function decode(Field $field, $value)
    {
        $class = get_class($field);

        $serializers = $this->getSerializers();

        if (!isset($serializers[$class])) {
            throw new MissingFieldSerializerException($field);
        }

        $serializer = $serializers[$class];

        return $serializer->decode($field, $value);
    }

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
}
