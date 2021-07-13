<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\EntitySerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\Exception\InvalidIdentifierException;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class EntitySerializerTest extends TestCase
{
    use KernelTestBehaviour;

    public function testSupportsAll(): void
    {
        $serializer = new EntitySerializer();

        $definitionRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);
        foreach ($definitionRegistry->getDefinitions() as $definition) {
            $entity = $definition->getEntityName();
            static::assertTrue(
                $serializer->supports($definition->getEntityName()),
                EntitySerializer::class . ' should support ' . $entity
            );
        }
    }

    public function testEnsureIdFields(): void
    {
        if (!Feature::isActive('FEATURE_NEXT_8097')) {
            static::markTestSkipped('NEXT-8097');
        }

        /** @var EntityDefinition $productDefinition */
        $productDefinition = $this->getContainer()->get(ProductDefinition::class);

        [$expectedData, $importData] = require __DIR__ . '/../../../fixtures/ensure_ids_for_products.php';

        $serializer = new EntitySerializer();
        $serializerRegistry = $this->getContainer()->get(SerializerRegistry::class);
        $serializer->setRegistry($serializerRegistry);
        $return = $serializer->deserialize(new Config([], []), $productDefinition, $importData);
        static::assertSame($expectedData, iterator_to_array($return));
    }

    public function testEnsureIdFieldsWithInvalidCharacter(): void
    {
        if (!Feature::isActive('FEATURE_NEXT_8097')) {
            static::markTestSkipped('NEXT-8097');
        }
        static::expectExceptionObject(new InvalidIdentifierException('invalid|string_with_pipe'));

        /** @var EntityDefinition $productDefinition */
        $productDefinition = $this->getContainer()->get(ProductDefinition::class);

        [$expectedData, $importData] = require __DIR__ . '/../../../fixtures/ensure_ids_for_products.php';
        $importData['id'] = 'invalid|string_with_pipe';

        $serializer = new EntitySerializer();
        $serializerRegistry = $this->getContainer()->get(SerializerRegistry::class);
        $serializer->setRegistry($serializerRegistry);
        $return = $serializer->deserialize(new Config([], []), $productDefinition, $importData);
        static::assertSame($expectedData, iterator_to_array($return));
    }

    public function testEnsureIdFieldsWithMixedContent(): void
    {
        if (!Feature::isActive('FEATURE_NEXT_8097')) {
            static::markTestSkipped('NEXT-8097');
        }

        /** @var EntityDefinition $productDefinition */
        $productDefinition = $this->getContainer()->get(ProductDefinition::class);

        [$expectedData, $importData] = require __DIR__ . '/../../../fixtures/ensure_ids_for_products.php';
        $importData['tax'] = [
            'id' => Uuid::randomHex(),
        ];
        $expectedData['categories'] = [
            [
                'id' => Uuid::randomHex(),
            ],
            [
                'id' => Uuid::randomHex(),
            ],
            [
                'id' => Uuid::randomHex(),
            ],
        ];
        $importData['categories'] = implode('|', array_column($expectedData['categories'], 'id'));
        $expectedData['tax'] = $importData['tax'];

        $serializer = new EntitySerializer();
        $serializerRegistry = $this->getContainer()->get(SerializerRegistry::class);
        $serializer->setRegistry($serializerRegistry);
        $return = $serializer->deserialize(new Config([], []), $productDefinition, $importData);
        static::assertSame($expectedData, iterator_to_array($return));
    }
}
