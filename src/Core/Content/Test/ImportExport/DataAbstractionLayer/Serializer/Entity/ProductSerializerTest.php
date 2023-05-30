<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\MediaSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\ProductSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\FieldSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\Exception\InvalidMediaUrlException;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('system-settings')]
class ProductSerializerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $visibilityRepository;

    private EntityRepository $salesChannelRepository;

    private EntityRepository $productMediaRepository;

    private EntityRepository $productConfiguratorSettingRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->visibilityRepository = $this->getContainer()->get('product_visibility.repository');
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $this->productMediaRepository = $this->getContainer()->get('product_media.repository');
        $this->productConfiguratorSettingRepository = $this->getContainer()->get('product_configurator_setting.repository');
    }

    public function testOnlySupportsProduct(): void
    {
        $serializer = new ProductSerializer(
            $this->visibilityRepository,
            $this->salesChannelRepository,
            $this->productMediaRepository,
            $this->productConfiguratorSettingRepository
        );

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

        $productDefinition = $this->getContainer()->get(ProductDefinition::class);

        $serializer = new ProductSerializer(
            $this->visibilityRepository,
            $this->salesChannelRepository,
            $this->productMediaRepository,
            $this->productConfiguratorSettingRepository
        );
        $serializer->setRegistry($this->getContainer()->get(SerializerRegistry::class));

        $serialized = iterator_to_array($serializer->serialize(new Config([], [], []), $productDefinition, $product));

        static::assertNotEmpty($serialized);

        static::assertSame($product->getId(), $serialized['id']);
        static::assertSame($product->getTranslations()->first()->getName(), $serialized['translations']['DEFAULT']['name']);
        static::assertSame((string) $product->getStock(), $serialized['stock']);
        static::assertSame($product->getProductNumber(), $serialized['productNumber']);
        static::assertSame('1', $serialized['active']);
        static::assertStringContainsString('shopware-logo.png', $serialized['cover']['media']['url']);
        static::assertStringContainsString('shopware-icon.png', $serialized['media']);
        static::assertStringContainsString('shopware-background.png', $serialized['media']);
        static::assertStringNotContainsString('shopware-logo.png', $serialized['media']);

        $deserialized = iterator_to_array($serializer->deserialize(new Config([], [], []), $productDefinition, $serialized));

        static::assertSame($product->getId(), $deserialized['id']);
        static::assertSame($product->getTranslations()->first()->getName(), $deserialized['translations'][Defaults::LANGUAGE_SYSTEM]['name']);
        static::assertSame($product->getStock(), $deserialized['stock']);
        static::assertSame($product->getProductNumber(), $deserialized['productNumber']);
        static::assertSame($product->getActive(), $deserialized['active']);
    }

    public function testSupportsOnlyProduct(): void
    {
        $serializer = new ProductSerializer(
            $this->visibilityRepository,
            $this->salesChannelRepository,
            $this->productMediaRepository,
            $this->productConfiguratorSettingRepository
        );

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

    public function testDeserializeProductMedia(): void
    {
        $product = $this->getProduct();

        $mediaService = $this->createMock(MediaService::class);
        $expectedMediaFile = new MediaFile(
            '/tmp/foo/bar/shopware-logo.png',
            'image/png',
            'png',
            1000,
            'bc0d90db4dd806bd671ae9f7fabc5796'
        );
        $mediaService->expects(static::any())
            ->method('fetchFile')
            ->willReturnCallback(function (Request $request) use ($expectedMediaFile): MediaFile {
                if ($request->get('url') === 'http://172.16.11.80/shopware-logo.png') {
                    return $expectedMediaFile;
                }

                return new MediaFile(
                    '/tmp/foo/bar/baz',
                    'image/png',
                    'png',
                    1000,
                    Uuid::randomHex()
                );
            });

        $fileSaver = $this->createMock(FileSaver::class);
        $mediaSerializer = new MediaSerializer(
            $mediaService,
            $fileSaver,
            $this->getContainer()->get('media_folder.repository'),
            $this->getContainer()->get('media.repository')
        );
        $mediaSerializer->setRegistry($this->getContainer()->get(SerializerRegistry::class));

        $serializerRegistry = $this->createMock(SerializerRegistry::class);
        $serializerRegistry->expects(static::any())
            ->method('getEntity')
            ->willReturn($mediaSerializer);
        $serializerRegistry->expects(static::any())
            ->method('getFieldSerializer')
            ->willReturn(new FieldSerializer());

        $record = [
            'id' => $product->getId(),
            'media' => 'http://172.16.11.80/shopware-logo.png|http://172.16.11.80/shopware-logo2.png',
        ];

        $productDefinition = $this->getContainer()->get(ProductDefinition::class);

        $serializer = new ProductSerializer(
            $this->visibilityRepository,
            $this->salesChannelRepository,
            $this->productMediaRepository,
            $this->productConfiguratorSettingRepository
        );
        $serializer->setRegistry($serializerRegistry);

        $result = $serializer->deserialize(new Config([], [], []), $productDefinition, $record);
        $result = \is_array($result) ? $result : iterator_to_array($result);

        static::assertEquals($product->getMedia()->first()->getId(), $result['media'][0]['id']);
        static::assertEquals($product->getMedia()->first()->getMedia()->getId(), $result['media'][0]['media']['id']);
        static::assertArrayNotHasKey('url', $result['media'][0]['media']);

        static::assertArrayNotHasKey('id', $result['media'][1]);
    }

    public function testDeserializeProductMediaWithInvalidUrl(): void
    {
        $record = [
            'media' => 'foo',
        ];

        $productDefinition = $this->getContainer()->get(ProductDefinition::class);

        $serializer = new ProductSerializer(
            $this->visibilityRepository,
            $this->salesChannelRepository,
            $this->productMediaRepository,
            $this->productConfiguratorSettingRepository
        );
        $serializer->setRegistry($this->getContainer()->get(SerializerRegistry::class));

        $result = $serializer->deserialize(new Config([], [], []), $productDefinition, $record);
        $result = \is_array($result) ? $result : iterator_to_array($result);

        static::assertArrayHasKey('_error', $result);
        static::assertInstanceOf(InvalidMediaUrlException::class, $result['_error']);
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
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
            'categories' => [
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'test category',
                ],
            ],
            'cover' => [
                'id' => Uuid::randomHex(),
                'position' => 0,
                'media' => [
                    'id' => Uuid::randomHex(),
                    'fileName' => 'shopware-logo',
                    'fileExtension' => 'png',
                    'mimeType' => 'image/png',
                    'metaData' => [
                        'hash' => 'bc0d90db4dd806bd671ae9f7fabc5796',
                    ],
                ],
            ],
            'media' => [
                [
                    'id' => Uuid::randomHex(),
                    'position' => 1,
                    'media' => [
                        'id' => Uuid::randomHex(),
                        'fileName' => 'shopware-icon',
                        'fileExtension' => 'png',
                        'mimeType' => 'image/png',
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'position' => 2,
                    'media' => [
                        'id' => Uuid::randomHex(),
                        'fileName' => 'shopware-background',
                        'fileExtension' => 'png',
                        'mimeType' => 'image/png',
                    ],
                ],
            ],
        ];

        /** @var EntityRepository $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $productRepository->create([$product], Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addAssociation('translations');
        $criteria->addAssociation('visibilities');
        $criteria->addAssociation('tax');
        $criteria->addAssociation('categories');
        $criteria->addAssociation('cover');
        $criteria->addAssociation('media');
        $criteria->getAssociation('media')->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));

        return $productRepository->search($criteria, Context::createDefaultContext())->first();
    }
}
