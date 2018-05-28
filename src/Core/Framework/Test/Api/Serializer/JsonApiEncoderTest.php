<?php declare(strict_types=1);

namespace Shopware\Framework\Test\Api\Serializer;

use PHPUnit\Framework\TestCase;
use Shopware\Content\Media\Aggregate\MediaAlbum\MediaAlbumDefinition;
use Shopware\Content\Media\Aggregate\MediaAlbum\Struct\MediaAlbumBasicStruct;
use Shopware\Content\Media\Aggregate\MediaAlbum\Struct\MediaAlbumDetailStruct;
use Shopware\Content\Media\MediaDefinition;
use Shopware\Content\Media\Util\UrlGeneratorInterface;
use Shopware\Framework\Api\Serializer\JsonApiEncoder;
use Shopware\Framework\Struct\Serializer\StructDecoder;
use Shopware\Framework\Struct\Serializer\StructNormalizer;
use Symfony\Component\Serializer\Exception\BadMethodCallException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

class JsonApiEncoderTest extends TestCase
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
        $this->encoder = new JsonApiEncoder(new StructDecoder());
        $this->structNormalizer = new StructNormalizer();
    }

    public function testSupportFormat(): void
    {
        $this->assertTrue($this->encoder->supportsEncoding('jsonapi'));
        $this->assertFalse($this->encoder->supportsEncoding('JSONAPI'));
        $this->assertFalse($this->encoder->supportsEncoding('yml'));
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
     */
    public function testEncodeWithEmptyInput($input): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Input is not iterable.');

        $this->encoder->encode($input, 'jsonapi');
    }

    public function testEncodeWithoutDefinition(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('The context key "definition" is required and must be an instance of Shopware\Framework\ORM\EntityDefinition.');

        $this->encoder->encode([], 'jsonapi', ['uri' => '/api']);
    }

    public function testEncodeWithoutBasicContext(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('The context key "basic" is required to indicate which type of struct should be encoded.');

        $this->encoder->encode([], 'jsonapi', ['uri' => '/api', 'definition' => MediaAlbumDefinition::class]);
    }

    public function testEncodeStruct(): void
    {
        $struct = new MediaAlbumBasicStruct();
        $struct->setId('1d23c1b0-15bf-43fb-97e8-9008cf42d6fe');
        $struct->setName('Manufacturer');
        $struct->setPosition(12);
        $struct->setCreatedAt(date_create_from_format(\DateTime::ATOM, '2018-01-15T08:01:16+00:00'));
        $struct->setCreateThumbnails(true);
        $struct->setThumbnailSize('200x200');
        $struct->setThumbnailQuality(90);
        $struct->setThumbnailHighDpi(true);
        $struct->setThumbnailHighDpiQuality(60);

        $struct = $this->structNormalizer->normalize($struct);

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
                    'versionId' => null,
                    'parentVersionId' => null,
                    'catalogId' => null,
                    'tenantId' => null,
                ],
                'links' => [
                    'self' => '/api/media-album/1d23c1b0-15bf-43fb-97e8-9008cf42d6fe',
                ],
            ],
            'included' => [],
        ];

        $this->assertEquals(
            $expected,
            json_decode(
                $this->encoder->encode($struct, 'jsonapi', ['uri' => '/api', 'definition' => MediaAlbumDefinition::class, 'basic' => true]),
                true
            )
        );
    }

    public function testEncodeStructWithEmptyRelation(): void
    {
        $struct = new MediaAlbumDetailStruct();
        $struct->setId('1d23c1b0-15bf-43fb-97e8-9008cf42d6fe');
        $struct->setName('Manufacturer');
        $struct->setPosition(12);
        $struct->setCreatedAt(date_create_from_format(\DateTime::ATOM, '2018-01-15T08:01:16+00:00'));
        $struct->setCreateThumbnails(true);
        $struct->setThumbnailSize('200x200');
        $struct->setThumbnailQuality(90);
        $struct->setThumbnailHighDpi(true);
        $struct->setThumbnailHighDpiQuality(60);

        $struct = $this->structNormalizer->normalize($struct);

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
                    'versionId' => null,
                    'parentVersionId' => null,
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
                ],
            ],
            'included' => [],
        ];

        $this->assertEquals(
            $expected,
            json_decode($this->encoder->encode($struct, 'jsonapi', ['uri' => '/api', 'definition' => MediaAlbumDefinition::class, 'basic' => false]), true)
        );
    }

    public function testEncodeStructWithToOneRelationship(): void
    {
        $struct = include __DIR__ . '/fixtures/testBasicWithToOneRelationship.php';
        $expected = include __DIR__ . '/fixtures/testBasicWithToOneRelationshipExpectation.php';
        $struct = $this->structNormalizer->normalize($struct);

        $urlGenerator = $this->getMockBuilder(UrlGeneratorInterface::class)->disableOriginalConstructor()->getMock();

        $this->assertEquals(
            $expected,
            json_decode($this->encoder->encode($struct, 'jsonapi', ['uri' => '/api', 'definition' => MediaDefinition::class, 'basic' => true]), true)
        );
    }

    public function testEncodeStructWithToManyRelationships(): void
    {
        $struct = include __DIR__ . '/fixtures/testBasicWithToManyRelationships.php';
        $expected = include __DIR__ . '/fixtures/testBasicWithToManyRelationshipsExpectation.php';
        $struct = $this->structNormalizer->normalize($struct);

        $urlGenerator = $this->getMockBuilder(UrlGeneratorInterface::class)->disableOriginalConstructor()->getMock();

        $result = json_decode($this->encoder->encode($struct, 'jsonapi', ['uri' => '/api', 'definition' => MediaAlbumDefinition::class, 'basic' => false]), true);

        $this->assertEquals($expected['data'], $result['data']);

        $this->assertCount(\count($expected['included']), $result['included']);
        $this->assertEquals($expected['included'], $result['included']);
        foreach ($expected['included'] as $include) {
            $this->assertContains($include, $result['included']);
        }
    }

    public function testEncodeCollectionWithToOneRelationship(): void
    {
        $collection = include __DIR__ . '/fixtures/testCollectionWithToOneRelationship.php';
        $expected = include __DIR__ . '/fixtures/testCollectionWithToOneRelationshipExpectation.php';
        $collection = $this->structNormalizer->normalize($collection);

        $urlGenerator = $this->getMockBuilder(UrlGeneratorInterface::class)->disableOriginalConstructor()->getMock();

        $this->assertEquals(
            $expected,
            json_decode($this->encoder->encode($collection, 'jsonapi', ['uri' => '/api', 'definition' => MediaDefinition::class, 'basic' => true]), true)
        );
    }

    public function testEncodeMainResourceShouldNotBeInIncluded(): void
    {
        $struct = include __DIR__ . '/fixtures/testMainResourceShouldNotBeInIncluded.php';
        $expected = include __DIR__ . '/fixtures/testMainResourceShouldNotBeInIncludedExpectation.php';
        $struct = $this->structNormalizer->normalize($struct);

        $urlGenerator = $this->getMockBuilder(UrlGeneratorInterface::class)->disableOriginalConstructor()->getMock();

        $this->assertEquals(
            $expected,
            json_decode($this->encoder->encode($struct, 'jsonapi', ['uri' => '/api', 'definition' => MediaAlbumDefinition::class, 'basic' => false]), true)
        );
    }
}
