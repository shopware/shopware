<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Serializer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Exception\UnsupportedEncoderInputException;
use Shopware\Core\Framework\Api\Serializer\JsonApiEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\SerializationFixture;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestBasicStruct;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestBasicWithExtension;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestBasicWithToManyExtension;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestBasicWithToManyRelationships;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestBasicWithToOneRelationship;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestCollectionWithSelfReference;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestCollectionWithToOneRelationship;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestInternalFieldsAreFiltered;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestMainResourceShouldNotBeInIncluded;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\AssociationExtension;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CustomFieldPlainTestDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendableDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedProductDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ProductExtension;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ScalarRuntimeExtension;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserDefinition;

/**
 * @internal
 */
class JsonApiEncoderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    private Connection $connection;

    private EntityRepository $productRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->registerDefinition(ExtendedProductDefinition::class);
        $this->registerDefinitionWithExtensions(
            ProductDefinition::class,
            ProductExtension::class
        );

        $this->productRepository = $this->getContainer()->get('product.repository');

        $this->connection->rollBack();

        $this->connection->executeStatement('
            DROP TABLE IF EXISTS `extended_product`;
            CREATE TABLE `extended_product` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NULL,
                `product_id` BINARY(16) NULL,
                `language_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.extended_product.id` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`),
                CONSTRAINT `fk.extended_product.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();

        $this->connection->executeStatement('DROP TABLE `extended_product`');
        $this->connection->beginTransaction();

        $this->removeExtension(ProductExtension::class);

        parent::tearDown();
    }

    /**
     * @return array<mixed>
     */
    public static function emptyInputProvider(): array
    {
        return [
            [null],
            ['string'],
            [1],
            [false],
            [new \DateTime()],
            [1.1],
        ];
    }

    /**
     * @param mixed $input
     *
     * @dataProvider emptyInputProvider
     */
    public function testEncodeWithEmptyInput($input): void
    {
        $this->expectException(UnsupportedEncoderInputException::class);

        $encoder = $this->getContainer()->get(JsonApiEncoder::class);
        $encoder->encode(new Criteria(), $this->getContainer()->get(ProductDefinition::class), $input, SerializationFixture::API_BASE_URL);
    }

    /**
     * @return array<array{string, SerializationFixture}>
     */
    public static function complexStructsProvider(): array
    {
        return [
            [MediaDefinition::class, new TestBasicStruct()],
            [UserDefinition::class, new TestBasicWithToManyRelationships()],
            [MediaDefinition::class, new TestBasicWithToOneRelationship()],
            [MediaFolderDefinition::class, new TestCollectionWithSelfReference()],
            [MediaDefinition::class, new TestCollectionWithToOneRelationship()],
            [RuleDefinition::class, new TestInternalFieldsAreFiltered()],
            [UserDefinition::class, new TestMainResourceShouldNotBeInIncluded()],
        ];
    }

    /**
     * @dataProvider complexStructsProvider
     */
    public function testEncodeComplexStructs(string $definitionClass, SerializationFixture $fixture): void
    {
        /** @var EntityDefinition $definition */
        $definition = $this->getContainer()->get($definitionClass);
        $encoder = $this->getContainer()->get(JsonApiEncoder::class);
        $actual = $encoder->encode(new Criteria(), $definition, $fixture->getInput(), SerializationFixture::API_BASE_URL);
        $actual = json_decode((string) $actual, true, 512, \JSON_THROW_ON_ERROR);

        // remove extensions from test
        $actual = $this->arrayRemove($actual, 'extensions');
        $actual['included'] = $this->removeIncludedExtensions($actual['included']);

        $this->assertValues($fixture->getAdminJsonApiFixtures(), $actual);
    }

    /**
     * Not possible with dataprovider
     * as we have to manipulate the container, but the dataprovider run before all tests
     */
    public function testEncodeStructWithExtension(): void
    {
        $this->registerDefinition(ExtendableDefinition::class, ExtendedDefinition::class);
        $extendableDefinition = new ExtendableDefinition();
        $extendableDefinition->addExtension(new AssociationExtension());
        $extendableDefinition->addExtension(new ScalarRuntimeExtension());

        $extendableDefinition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));
        $fixture = new TestBasicWithExtension();

        $encoder = $this->getContainer()->get(JsonApiEncoder::class);
        $actual = $encoder->encode(new Criteria(), $extendableDefinition, $fixture->getInput(), SerializationFixture::API_BASE_URL);

        // check that empty "links" object is an object and not array: https://jsonapi.org/format/#document-links
        static::assertStringNotContainsString('"links":[]', $actual);
        static::assertStringContainsString('"links":{}', $actual);

        $this->assertValues($fixture->getAdminJsonApiFixtures(), json_decode((string) $actual, true, 512, \JSON_THROW_ON_ERROR));
    }

    /**
     * Not possible with dataprovider
     * as we have to manipulate the container, but the dataprovider run before all tests
     */
    public function testEncodeStructWithToManyExtension(): void
    {
        $this->registerDefinition(ExtendableDefinition::class, ExtendedDefinition::class);
        $extendableDefinition = new ExtendableDefinition();
        $extendableDefinition->addExtension(new AssociationExtension());

        $extendableDefinition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));
        $fixture = new TestBasicWithToManyExtension();

        $encoder = $this->getContainer()->get(JsonApiEncoder::class);
        $actual = $encoder->encode(new Criteria(), $extendableDefinition, $fixture->getInput(), SerializationFixture::API_BASE_URL);

        // check that empty "links" object is an object and not array: https://jsonapi.org/format/#document-links
        static::assertStringNotContainsString('"links":[]', $actual);
        static::assertStringContainsString('"links":{}', $actual);

        // check that empty "attributes" object is an object and not array: https://jsonapi.org/format/#document-resource-object-attributes
        static::assertStringNotContainsString('"attributes":[]', $actual);
        static::assertStringContainsString('"attributes":{}', $actual);

        $this->assertValues($fixture->getAdminJsonApiFixtures(), json_decode((string) $actual, true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testEncodeEntityWithToOneEntityExtension(): void
    {
        $productId = Uuid::randomHex();

        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test product',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'shopware AG',
                    'link' => 'https://shopware.com',
                ],
                'toOne' => [
                    'name' => 'test',
                ],
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('toOne');

        $productDefinition = $this->getContainer()->get(ProductDefinition::class);

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->get($productId);
        $encoder = $this->getContainer()->get(JsonApiEncoder::class);
        $encodedResponse = $encoder->encode(new Criteria(), $productDefinition, $product, SerializationFixture::API_BASE_URL);
        $actual = json_decode((string) $encodedResponse, true, 512, \JSON_THROW_ON_ERROR);

        foreach ($actual['included'] as $included) {
            if ($included['type'] !== 'extension') {
                continue;
            }
            static::assertNotEmpty($included['relationships']['toOne']['data'], 'The relationship data to the loaded extension association is missing');
            static::assertEquals('extended_product', $included['relationships']['toOne']['data']['type']);
            static::assertNotEmpty($included['relationships']['toOne']['data']['id']);
        }
    }

    public function testEncodeEntityWithToManyEntityExtension(): void
    {
        $productId = Uuid::randomHex();

        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test product',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'shopware AG',
                    'link' => 'https://shopware.com',
                ],
                'oneToMany' => [
                    [
                        'name' => 'toMany01',
                    ],
                    [
                        'name' => 'toMany02',
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('oneToMany');

        $productDefinition = $this->getContainer()->get(ProductDefinition::class);

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->get($productId);
        $encoder = $this->getContainer()->get(JsonApiEncoder::class);
        $encodedResponse = $encoder->encode(new Criteria(), $productDefinition, $product, SerializationFixture::API_BASE_URL);
        $actual = json_decode((string) $encodedResponse, true, 512, \JSON_THROW_ON_ERROR);

        foreach ($actual['included'] as $included) {
            if ($included['type'] !== 'extension') {
                continue;
            }
            static::assertNotEmpty($included['relationships']['oneToMany']['data'], 'The relationship data to the loaded extension association is missing');
            static::assertCount(2, $included['relationships']['oneToMany']['data']);
            static::assertEquals('extended_product', $included['relationships']['oneToMany']['data'][0]['type']);
            static::assertNotEmpty($included['relationships']['oneToMany']['data'][0]['id']);
        }
    }

    /**
     * @param array<mixed> $input
     * @param array<mixed>|null $output
     *
     * @dataProvider customFieldsProvider
     */
    public function testCustomFields(array $input, $output): void
    {
        $encoder = $this->getContainer()->get(JsonApiEncoder::class);

        $definition = new CustomFieldPlainTestDefinition();
        $definition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));
        $struct = new Entity();
        $struct->setUniqueIdentifier(Uuid::randomHex());
        $struct->assign($input);

        $actual = json_decode((string) $encoder->encode(new Criteria(), $definition, $struct, SerializationFixture::API_BASE_URL), true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals($output, $actual['data']['attributes']['customFields']);
    }

    public static function customFieldsProvider(): \Generator
    {
        yield 'Custom field null' => [
            [
                'customFields' => null,
            ],
            null,
        ];

        yield 'Custom field with empty array' => [
            [
                'customFields' => [],
            ],
            [],
        ];

        yield 'Custom field with values' => [
            [
                'customFields' => ['bla'],
            ],
            ['bla'],
        ];
    }

    /**
     * @param array<mixed> $haystack
     *
     * @return array<mixed>
     */
    private function arrayRemove(array $haystack, string $keyToRemove): array
    {
        foreach ($haystack as $key => $value) {
            if (\is_array($value)) {
                $haystack[$key] = $this->arrayRemove($haystack[$key], $keyToRemove);
            }

            if ($key === $keyToRemove) {
                unset($haystack[$key]);
            }
        }

        return $haystack;
    }

    /**
     * @param array<array<mixed>> $array
     *
     * @return array<array<mixed>>
     */
    private function removeIncludedExtensions($array): array
    {
        $filtered = [];
        foreach ($array as $item) {
            if ($item['type'] !== 'extension') {
                $filtered[] = $item;
            }
        }

        return $filtered;
    }

    /**
     * @param array<mixed> $expected
     * @param array<mixed> $actual
     */
    private function assertValues(array $expected, array $actual): void
    {
        foreach ($expected as $key => $value) {
            static::assertArrayHasKey($key, $actual);

            if (\is_array($value)) {
                $this->assertValues($value, $actual[$key]);
            } else {
                static::assertEquals($value, $actual[$key], 'Key: ' . $key);
            }
        }
    }
}
