<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
    use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
    use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
    use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
    use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
    use Shopware\Core\Framework\Uuid\Uuid;
    use Symfony\Component\Validator\ConstraintViolationList;
    use Symfony\Component\Validator\Validator\ValidatorInterface;
    use TestSerializer\TestFieldSerializer;

    class AbstractFieldSerializerTest extends TestCase
    {
        public function testGetConstraintsOnlyCalledOnce(): void
        {
            $validator = $this->createMock(ValidatorInterface::class);
            $validator->method('validate')->willReturn(new ConstraintViolationList());
            $serializer = new TestFieldSerializer(
                $validator,
                $this->createMock(DefinitionInstanceRegistry::class)
            );

            static::assertSame(0, $serializer->getConstraintsCallCounter);
            $entityExistence = new EntityExistence('test', ['id' => Uuid::randomHex()], true, false, false, []);
            $field = $this->createMock(Field::class);

            $data = new KeyValuePair('foo', 'bar', true);

            static::assertNotNull($serializer->encode($field, $entityExistence, $data, $this->createMock(WriteParameterBag::class))->current());
            static::assertSame(1, $serializer->getConstraintsCallCounter);

            static::assertNotNull($serializer->encode($field, $entityExistence, $data, $this->createMock(WriteParameterBag::class))->current());
            static::assertSame(1, $serializer->getConstraintsCallCounter);
        }

        public function testCaching(): void
        {
            $validator = $this->createMock(ValidatorInterface::class);
            $validator->method('validate')->willReturn(new ConstraintViolationList());
            $serializer = new TestFieldSerializer(
                $validator,
                $this->createMock(DefinitionInstanceRegistry::class)
            );
            $parameters = $this->createMock(WriteParameterBag::class);

            static::assertSame(0, $serializer->getConstraintsCallCounter);
            $entityExistence = new EntityExistence('test', ['id' => Uuid::randomHex()], true, false, false, []);

            $data = new KeyValuePair('foo', 'bar', true);
            $field = $this->createMock(Field::class);
            static::assertNotNull($serializer->encode($field, $entityExistence, $data, $parameters)->current());
            static::assertSame(1, $serializer->getConstraintsCallCounter);

            $serializer->getConstraintsCallCounter = 0;
            $newField = $this->createMock(Field::class);
            // a different field object should not return the cached constraints of the other field
            static::assertNotNull($serializer->encode($newField, $entityExistence, $data, $parameters)->current());
            static::assertSame(1, $serializer->getConstraintsCallCounter);
        }
    }
}

namespace TestSerializer {
    use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
    use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\AbstractFieldSerializer;
    use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
    use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
    use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
    use Symfony\Component\Validator\Constraints\NotBlank;

    class TestFieldSerializer extends AbstractFieldSerializer
    {
        public int $getConstraintsCallCounter = 0;

        public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
        {
            $this->validateIfNeeded($field, $existence, $data, $parameters);

            yield $data->getKey() => $data->getValue();
        }

        public function decode(Field $field, $value)
        {
            return $value;
        }

        protected function getConstraints(Field $field): array
        {
            ++$this->getConstraintsCallCounter;

            return [new NotBlank()];
        }
    }
}
