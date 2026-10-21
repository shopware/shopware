<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\ProductCrossSellingSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\AbstractFieldSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ProductCrossSellingSerializer::class)]
class ProductCrossSellingSerializerTest extends TestCase
{
    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $expected
     */
    #[DataProvider('deserializeProvider')]
    public function testDeserializeAssignedProducts(array $record, array $expected): void
    {
        $fieldSerializer = static::createStub(AbstractFieldSerializer::class);
        $fieldSerializer->method('deserialize')->willReturnCallback(
            static fn (Config $config, Field $field, mixed $value): mixed => $value
        );
        $registry = static::createStub(SerializerRegistry::class);
        $registry->method('getFieldSerializer')->willReturn($fieldSerializer);

        $serializer = new ProductCrossSellingSerializer(static::createStub(EntityRepository::class));
        $serializer->setRegistry($registry);

        $definition = (new StaticDefinitionInstanceRegistry(
            [ProductCrossSellingDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        ))->getByEntityName(ProductCrossSellingDefinition::ENTITY_NAME);

        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $result = iterator_to_array($serializer->deserialize(new Config([], [], []), $definition, $record));
        } finally {
            restore_error_handler();
        }

        static::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{record: array<string, mixed>, expected: array<string, mixed>}>
     */
    public static function deserializeProvider(): iterable
    {
        yield 'assignedProducts absent' => [
            'record' => ['type' => 'productList'],
            'expected' => ['type' => 'productList'],
        ];

        yield 'assignedProducts null' => [
            'record' => ['assignedProducts' => null],
            'expected' => [],
        ];

        yield 'assignedProducts empty array' => [
            'record' => ['assignedProducts' => []],
            'expected' => ['assignedProducts' => []],
        ];

        yield 'assignedProducts empty string' => [
            'record' => ['assignedProducts' => ''],
            'expected' => ['assignedProducts' => ''],
        ];

        yield 'assignedProducts string zero' => [
            'record' => ['assignedProducts' => '0'],
            'expected' => ['assignedProducts' => '0'],
        ];

        yield 'assignedProducts non-empty string' => [
            'record' => ['assignedProducts' => 'abc'],
            'expected' => ['assignedProducts' => 'abc'],
        ];

        yield 'assignedProducts list without cross selling id' => [
            'record' => ['assignedProducts' => ['product-a', 'product-b']],
            'expected' => ['assignedProducts' => [
                ['productId' => 'product-a', 'crossSellingId' => null, 'position' => 0],
                ['productId' => 'product-b', 'crossSellingId' => null, 'position' => 1],
            ]],
        ];
    }
}
