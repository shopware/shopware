<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Serializer;

use Shopware\Core\Content\Media\Aggregate\MediaAlbum\MediaAlbumDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaAlbum\MediaAlbumStruct;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Exception\UnsupportedEncoderInputException;
use Shopware\Core\Framework\Api\Serializer\JsonApiEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Serializer\StructNormalizer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class JsonApiEncoderTest extends KernelTestCase
{
    /**
     * @var JsonApiEncoder
     */
    private $encoder;

    /**
     * @var StructNormalizer
     */
    private $structNormalizer;

    public function setUp()
    {
        self::bootKernel();
        $this->encoder = new JsonApiEncoder();
    }

    public function emptyInputProvider(): array
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
     * @dataProvider emptyInputProvider
     *
     * @param mixed $input
     *
     * @throws UnsupportedEncoderInputException
     */
    public function testEncodeWithEmptyInput($input): void
    {
        $this->expectException(UnsupportedEncoderInputException::class);

        $this->encoder->encode(ProductDefinition::class, $input, Context::createDefaultContext(Defaults::TENANT_ID), '');
    }

    public function testEncodeStruct(): void
    {
        $struct = new MediaAlbumStruct();
        $struct->setId('1d23c1b0-15bf-43fb-97e8-9008cf42d6fe');
        $struct->setName('Manufacturer');
        $struct->setPosition(12);
        $struct->setCreatedAt(date_create_from_format(\DateTime::ATOM, '2018-01-15T08:01:16+00:00'));
        $struct->setCreateThumbnails(true);
        $struct->setThumbnailSize('200x200');
        $struct->setThumbnailQuality(90);
        $struct->setThumbnailHighDpi(true);
        $struct->setThumbnailHighDpiQuality(60);

        $expected = [
            'data' => [
                'id' => '1d23c1b0-15bf-43fb-97e8-9008cf42d6fe',
                'type' => 'media_album',
                'attributes' => [
                    'parentId' => null,
                    'name' => 'Manufacturer',
                    'position' => 12,
                    'createThumbnails' => true,
                    'thumbnailSize' => '200x200',
                    'icon' => null,
                    'thumbnailHighDpi' => true,
                    'thumbnailQuality' => 90,
                    'thumbnailHighDpiQuality' => 60,
                    'createdAt' => '2018-01-15T08:01:16+00:00',
                    'updatedAt' => null,
                    'catalogId' => null,
                    'tenantId' => null,
                ],
                'links' => [
                    'self' => '/api/media-album/1d23c1b0-15bf-43fb-97e8-9008cf42d6fe',
                ],
                'relationships' => [
                    'parent' => [
                        'data' => null,
                        'links' => [
                            'related' => '/api/media-album/1d23c1b0-15bf-43fb-97e8-9008cf42d6fe/parent',
                        ],
                    ],
                    'media' => [
                        'data' => [],
                        'links' => [
                            'related' => '/api/media-album/1d23c1b0-15bf-43fb-97e8-9008cf42d6fe/media',
                        ],
                    ],
                    'children' => [
                        'data' => [],
                        'links' => [
                            'related' => '/api/media-album/1d23c1b0-15bf-43fb-97e8-9008cf42d6fe/children',
                        ],
                    ],
                    'catalog' => [
                        'data' => null,
                        'links' => [
                            'related' => '/api/media-album/1d23c1b0-15bf-43fb-97e8-9008cf42d6fe/catalog',
                        ],
                    ],
                ],
            ],
            'included' => [],
        ];

        $actual = $this->encoder->encode(MediaAlbumDefinition::class, $struct, Context::createDefaultContext(Defaults::TENANT_ID), '');
        static::assertEquals($expected, json_decode($actual, true));
    }

    public function testEncodeStructWithEmptyRelation(): void
    {
        $struct = new MediaAlbumStruct();
        $struct->setId('1d23c1b0-15bf-43fb-97e8-9008cf42d6fe');
        $struct->setName('Manufacturer');
        $struct->setPosition(12);
        $struct->setCreatedAt(date_create_from_format(\DateTime::ATOM, '2018-01-15T08:01:16+00:00'));
        $struct->setCreateThumbnails(true);
        $struct->setThumbnailSize('200x200');
        $struct->setThumbnailQuality(90);
        $struct->setThumbnailHighDpi(true);
        $struct->setThumbnailHighDpiQuality(60);

        $expected = [
            'data' => [
                'id' => '1d23c1b0-15bf-43fb-97e8-9008cf42d6fe',
                'type' => 'media_album',
                'attributes' => [
                    'parentId' => null,
                    'name' => 'Manufacturer',
                    'position' => 12,
                    'createThumbnails' => true,
                    'thumbnailSize' => '200x200',
                    'icon' => null,
                    'thumbnailHighDpi' => true,
                    'thumbnailQuality' => 90,
                    'thumbnailHighDpiQuality' => 60,
                    'createdAt' => '2018-01-15T08:01:16+00:00',
                    'updatedAt' => null,
                    'catalogId' => null,
                    'tenantId' => null,
                ],
                'links' => [
                    'self' => '/api/media-album/1d23c1b0-15bf-43fb-97e8-9008cf42d6fe',
                ],
                'relationships' => [
                    'media' => [
                        'links' => [
                            'related' => '/api/media-album/1d23c1b0-15bf-43fb-97e8-9008cf42d6fe/media',
                        ],
                        'data' => [],
                    ],
                    'parent' => [
                        'links' => [
                            'related' => '/api/media-album/1d23c1b0-15bf-43fb-97e8-9008cf42d6fe/parent',
                        ],
                        'data' => null,
                    ],
                    'children' => [
                        'links' => [
                            'related' => '/api/media-album/1d23c1b0-15bf-43fb-97e8-9008cf42d6fe/children',
                        ],
                        'data' => [],
                    ],
                    'catalog' => [
                        'data' => null,
                        'links' => [
                            'related' => '/api/media-album/1d23c1b0-15bf-43fb-97e8-9008cf42d6fe/catalog',
                        ],
                    ],
                ],
            ],
            'included' => [],
        ];

        $actual = $this->encoder->encode(MediaAlbumDefinition::class, $struct, Context::createDefaultContext(Defaults::TENANT_ID), '');
        static::assertEquals($expected, json_decode($actual, true));
    }

    public function testEncodeStructWithToOneRelationship(): void
    {
        $struct = include __DIR__ . '/fixtures/testBasicWithToOneRelationship.php';
        $expected = include __DIR__ . '/fixtures/testBasicWithToOneRelationshipExpectation.php';

        $actual = $this->encoder->encode(MediaDefinition::class, $struct, Context::createDefaultContext(Defaults::TENANT_ID), '');
        static::assertEquals($expected, json_decode($actual, true));
    }

    public function testEncodeStructWithToManyRelationships(): void
    {
        $struct = include __DIR__ . '/fixtures/testBasicWithToManyRelationships.php';
        $expected = include __DIR__ . '/fixtures/testBasicWithToManyRelationshipsExpectation.php';

        $actual = $this->encoder->encode(MediaAlbumDefinition::class, $struct, Context::createDefaultContext(Defaults::TENANT_ID), '');

        static::assertEquals($expected, json_decode($actual, true));
    }

    public function testEncodeCollectionWithToOneRelationship(): void
    {
        $collection = include __DIR__ . '/fixtures/testCollectionWithToOneRelationship.php';
        $expected = include __DIR__ . '/fixtures/testCollectionWithToOneRelationshipExpectation.php';

        $actual = $this->encoder->encode(MediaDefinition::class, $collection, Context::createDefaultContext(Defaults::TENANT_ID), '');
        static::assertEquals($expected, json_decode($actual, true));
    }

    public function testEncodeMainResourceShouldNotBeInIncluded(): void
    {
        $struct = include __DIR__ . '/fixtures/testMainResourceShouldNotBeInIncluded.php';
        $expected = include __DIR__ . '/fixtures/testMainResourceShouldNotBeInIncludedExpectation.php';

        $actual = $this->encoder->encode(MediaAlbumDefinition::class, $struct, Context::createDefaultContext(Defaults::TENANT_ID), '');
        static::assertEquals($expected, json_decode($actual, true));
    }
}
