<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\ProductSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductSerializerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testOnlySupportsProduct(): void
    {
        $visibilityRepository = $this->getContainer()->get('product_visibility.repository');

        $serializer = new ProductSerializer($visibilityRepository);

        static::assertTrue($serializer->supports('product'), 'should support product');

        $definitionRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);
        foreach ($definitionRegistry->getDefinitions() as $definition) {
            $entity = $definition->getEntityName();
            if ($entity !== 'product') {
                static::assertFalse(
                    $serializer->supports($definition->getEntityName()),
                    ProductSerializer::class . ' should not support ' . $entity
                );
            }
        }
    }

    public function testProductSerialize(): void
    {
        $product = $this->getProduct();

        $visibilityRepository = $this->getContainer()->get('product_visibility.repository');

        $productDefinition = $this->getContainer()->get(ProductDefinition::class);

        $serializer = new ProductSerializer($visibilityRepository);
        $serializer->setRegistry($this->getContainer()->get(SerializerRegistry::class));

        $serialized = iterator_to_array($serializer->serialize(new Config([], []), $productDefinition, $product));

        static::assertNotEmpty($serialized);

        static::assertSame($product->getId(), $serialized['id']);
        static::assertSame($product->getTranslations()->first()->getName(), $serialized['translations']['DEFAULT']['name']);
        static::assertSame((string) $product->getStock(), $serialized['stock']);
        static::assertSame($product->getProductNumber(), $serialized['productNumber']);
        static::assertSame('1', $serialized['active']);

        $deserialized = iterator_to_array($serializer->deserialize(new Config([], []), $productDefinition, $serialized));

        static::assertSame($product->getId(), $deserialized['id']);
        static::assertSame($product->getTranslations()->first()->getName(), $deserialized['translations'][Defaults::LANGUAGE_SYSTEM]['name']);
        static::assertSame($product->getStock(), $deserialized['stock']);
        static::assertSame($product->getProductNumber(), $deserialized['productNumber']);
        static::assertSame($product->getActive(), $deserialized['active']);
    }

    public function testSupportsOnlyProduct(): void
    {
        $serializer = new ProductSerializer($this->getContainer()->get('product_visibility.repository'));

        $definitionRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);
        foreach ($definitionRegistry->getDefinitions() as $definition) {
            $entity = $definition->getEntityName();

            if ($entity === ProductDefinition::ENTITY_NAME) {
                static::assertTrue($serializer->supports($entity));
            } else {
                static::assertFalse(
                    $serializer->supports($entity),
                    ProductDefinition::class . ' should not support ' . $entity
                );
            }
        }
    }

    private function getProduct(): ProductEntity
    {
        $productId = Uuid::randomHex();

        $product = [
            'id' => $productId,
            'stock' => 101,
            'productNumber' => 'P101',
            'active' => true,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'name' => 'test product',
                ],
            ],
            'tax' => [
                'name' => '19%',
                'taxRate' => 19.0,
            ],
            'price' => [
                Defaults::CURRENCY => [
                    'gross' => 1.111,
                    'net' => 1.011,
                    'linked' => true,
                    'currencyId' => Defaults::CURRENCY,
                    'listPrice' => [
                        'gross' => 1.111,
                        'net' => 1.011,
                        'linked' => false,
                        'currencyId' => Defaults::CURRENCY,
                    ],
                ],
            ],
            'visibilities' => [
                [
                    'salesChannelId' => Defaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
            'categories' => [
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'test category',
                ],
            ],
        ];

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $productRepository->create([$product], Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addAssociation('translations');
        $criteria->addAssociation('visibilities');
        $criteria->addAssociation('tax');
        $criteria->addAssociation('categories');

        return $productRepository->search($criteria, Context::createDefaultContext())->first();
    }
}
