<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\IdFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(IdFieldSerializer::class)]
class IdFieldSerializerTest extends TestCase
{
    #[DataProvider('idShouldNotBeGeneratedCasesProvider')]
    public function testSerializerAcceptsValue(Field $field, ?string $value, ?string $expected): void
    {
        $serializer = $this->getIdFieldSerializer();

        $existence = EntityExistence::createEmpty();
        $kv = new KeyValuePair($field->getPropertyName(), $value, true);
        $params = $this->getWriteParameterBag();

        $encoded = iterator_to_array($serializer->encode($field, $existence, $kv, $params));

        static::assertArrayHasKey('media_id', $encoded);

        static::assertSame($expected, $encoded['media_id']);
    }

    public static function idShouldNotBeGeneratedCasesProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield 'field is pk and specified' => [
            'field' => (new IdField('media_id', 'mediaId'))->addFlags(new PrimaryKey()),
            'value' => $ids->get('media-1'),
            'expected' => $ids->getBytes('media-1'),
        ];

        yield 'field is required and specified' => [
            'field' => (new IdField('media_id', 'mediaId'))->addFlags(new Required()),
            'value' => $ids->get('media-1'),
            'expected' => $ids->getBytes('media-1'),
        ];

        yield 'field is pk and required and specified' => [
            'field' => (new IdField('media_id', 'mediaId'))->addFlags(new PrimaryKey(), new Required()),
            'value' => $ids->get('media-1'),
            'expected' => $ids->getBytes('media-1'),
        ];

        yield 'field is not required, nor pk (null should be accepted)' => [
            'field' => new IdField('media_id', 'mediaId'),
            'value' => null,
            'expected' => null,
        ];
    }

    #[DataProvider('idShouldBeGeneratedCasesProvider')]
    public function testSerializerGeneratesValueWhenNullIsPassed(Field $field): void
    {
        $serializer = $this->getIdFieldSerializer();

        $existence = EntityExistence::createEmpty();
        $kv = new KeyValuePair($field->getPropertyName(), null, true);
        $params = $this->getWriteParameterBag();

        $encoded = iterator_to_array($serializer->encode($field, $existence, $kv, $params));

        static::assertArrayHasKey('media_id', $encoded);

        static::assertTrue(Uuid::isValid(Uuid::fromBytesToHex($encoded['media_id'])));
    }

    public static function idShouldBeGeneratedCasesProvider(): \Generator
    {
        yield 'field is pk and not explicitly passed' => [
            'field' => (new IdField('media_id', 'mediaId'))->addFlags(new PrimaryKey()),
        ];

        yield 'field is required and not explicitly passed' => [
            'field' => (new IdField('media_id', 'mediaId'))->addFlags(new Required()),
        ];

        yield 'field is pk and required and not explicitly passed' => [
            'field' => (new IdField('media_id', 'mediaId'))->addFlags(new PrimaryKey(), new Required()),
        ];
    }

    public function testNormalizeForPrimaryKeyWithProvidedValue(): void
    {
        $serializer = $this->getIdFieldSerializer();
        $params = $this->getWriteParameterBag();

        $field = (new IdField('media_id', 'mediaId'))->addFlags(new PrimaryKey());
        $data = ['mediaId' => Uuid::randomHex()];

        $normalized = $serializer->normalize($field, $data, $params);

        static::assertSame($data, $normalized);
        static::assertSame($data['mediaId'], $params->getContext()->get('product', 'mediaId'));
    }

    public function testNormalizeForPrimaryKeyGeneratesIdIfNotPassed(): void
    {
        $serializer = $this->getIdFieldSerializer();
        $params = $this->getWriteParameterBag();

        $field = (new IdField('media_id', 'mediaId'))->addFlags(new PrimaryKey());
        $data = [];

        $normalized = $serializer->normalize($field, $data, $params);

        static::assertArrayHasKey('mediaId', $normalized);
        static::assertTrue(Uuid::isValid($normalized['mediaId']));
        static::assertSame($normalized['mediaId'], $params->getContext()->get('product', 'mediaId'));
    }

    public function testNormalizeForNonPrimaryKeyWithProvidedValue(): void
    {
        $serializer = $this->getIdFieldSerializer();
        $params = $this->getWriteParameterBag();

        $field = new IdField('media_id', 'mediaId');
        $data = ['mediaId' => Uuid::randomHex()];

        $normalized = $serializer->normalize($field, $data, $params);

        static::assertSame($data, $normalized);
        static::assertFalse($params->getContext()->has('product', 'mediaId'));
    }

    public function testNormalizeForNonPrimaryKeyDoesNotGenerateValue(): void
    {
        $serializer = $this->getIdFieldSerializer();
        $params = $this->getWriteParameterBag();

        $field = new IdField('media_id', 'mediaId');
        $data = [];

        $normalized = $serializer->normalize($field, $data, $params);

        static::assertSame($data, $normalized);
        static::assertFalse($params->getContext()->has('product', 'mediaId'));
    }

    private function getIdFieldSerializer(): IdFieldSerializer
    {
        $validator = $this->createMock(ValidatorInterface::class);

        return new IdFieldSerializer(
            $validator,
            new StaticDefinitionInstanceRegistry([], $validator, $this->createMock(EntityWriteGatewayInterface::class))
        );
    }

    private function getWriteParameterBag(): WriteParameterBag
    {
        return new WriteParameterBag(
            new ProductDefinition(),
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );
    }
}
