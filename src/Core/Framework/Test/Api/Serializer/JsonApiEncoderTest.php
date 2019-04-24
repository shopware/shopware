<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Serializer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\Api\Exception\UnsupportedEncoderInputException;
use Shopware\Core\Framework\Api\Serializer\JsonApiEncoder;
use Shopware\Core\System\User\UserDefinition;

class JsonApiEncoderTest extends TestCase
{
    /**
     * @var JsonApiEncoder
     */
    private $encoder;

    protected function setUp(): void
    {
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
     */
    public function testEncodeWithEmptyInput($input): void
    {
        $this->expectException(UnsupportedEncoderInputException::class);

        $this->encoder->encode(ProductDefinition::class, $input, '/api');
    }

    public function testEncodeStruct(): void
    {
        $struct = new MediaEntity();
        $struct->setId('1d23c1b015bf43fb97e89008cf42d6fe');
        $struct->setTitle('Manufacturer');
        $struct->setMimeType('image/png');
        $struct->setFileExtension('png');
        $struct->setFileSize(310818);

        $struct->setAlt('A media object description');

        $struct->setCreatedAt(new \DateTime('2018-01-15T08:01:16+00:00'));

        $expected = [
            'data' => [
                'id' => '1d23c1b015bf43fb97e89008cf42d6fe',
                'type' => 'media',
                'attributes' => [
                    'userId' => null,
                    'mediaFolderId' => null,
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'uploadedAt' => null,
                    'fileName' => null,
                    'fileSize' => 310818,
                    'metaData' => null,
                    'mediaType' => null,
                    'createdAt' => '2018-01-15T08:01:16+00:00',
                    'updatedAt' => null,
                    'alt' => 'A media object description',
                    'title' => 'Manufacturer',
                    'url' => '',
                    'attributes' => null,
                    'hasFile' => false,
                    'translated' => [],
                ],
                'links' => [
                    'self' => '/api/media/1d23c1b015bf43fb97e89008cf42d6fe',
                ],
                'relationships' => [
                    'user' => [
                        'data' => null,
                        'links' => [
                            'related' => '/api/media/1d23c1b015bf43fb97e89008cf42d6fe/user',
                        ],
                    ],
                    'categories' => [
                        'data' => [],
                        'links' => [
                            'related' => '/api/media/1d23c1b015bf43fb97e89008cf42d6fe/categories',
                        ],
                    ],
                    'productManufacturers' => [
                        'data' => [],
                        'links' => [
                            'related' => '/api/media/1d23c1b015bf43fb97e89008cf42d6fe/product-manufacturers',
                        ],
                    ],
                    'productMedia' => [
                        'data' => [],
                        'links' => [
                            'related' => '/api/media/1d23c1b015bf43fb97e89008cf42d6fe/product-media',
                        ],
                    ],
                    'avatarUser' => [
                        'data' => null,
                        'links' => [
                            'related' => '/api/media/1d23c1b015bf43fb97e89008cf42d6fe/avatar-user',
                        ],
                    ],
                    'translations' => [
                        'data' => [],
                        'links' => [
                            'related' => '/api/media/1d23c1b015bf43fb97e89008cf42d6fe/translations',
                        ],
                    ],
                    'thumbnails' => [
                        'data' => [],
                        'links' => [
                            'related' => '/api/media/1d23c1b015bf43fb97e89008cf42d6fe/thumbnails',
                        ],
                    ],
                    'mediaFolder' => [
                        'data' => null,
                        'links' => [
                            'related' => '/api/media/1d23c1b015bf43fb97e89008cf42d6fe/media-folder',
                        ],
                    ],
                    'propertyGroupOptions' => [
                        'data' => [],
                        'links' => [
                            'related' => '/api/media/1d23c1b015bf43fb97e89008cf42d6fe/property-group-options',
                        ],
                    ],
                    'tags' => [
                        'data' => [],
                        'links' => [
                            'related' => '/api/media/1d23c1b015bf43fb97e89008cf42d6fe/tags',
                        ],
                    ],
                    'mailTemplateMedia' => [
                        'data' => [],
                        'links' => [
                            'related' => '/api/media/1d23c1b015bf43fb97e89008cf42d6fe/mail-template-media',
                        ],
                    ],
                    'shippingMethods' => [
                        'data' => [],
                        'links' => [
                            'related' => '/api/media/1d23c1b015bf43fb97e89008cf42d6fe/shipping-methods',
                        ],
                    ],
                    'paymentMethods' => [
                        'data' => [],
                        'links' => [
                            'related' => '/api/media/1d23c1b015bf43fb97e89008cf42d6fe/payment-methods',
                        ],
                    ],
                    'productConfiguratorSettings' => [
                        'data' => [],
                        'links' => [
                            'related' => '/api/media/1d23c1b015bf43fb97e89008cf42d6fe/product-configurator-settings',
                        ],
                    ],
                    'orderLineItems' => [
                        'data' => [],
                        'links' => [
                            'related' => '/api/media/1d23c1b015bf43fb97e89008cf42d6fe/order-line-items',
                        ],
                    ],
                ],
                'meta' => null,
            ],
            'included' => [],
        ];

        $actual = $this->encoder->encode(MediaDefinition::class, $struct, '/api');
        static::assertEquals($expected, json_decode($actual, true));
    }

    public function testEncodeStructWithToOneRelationship(): void
    {
        $struct = include __DIR__ . '/fixtures/testBasicWithToOneRelationship.php';
        $expected = include __DIR__ . '/fixtures/testBasicWithToOneRelationshipExpectation.php';

        $actual = $this->encoder->encode(MediaDefinition::class, $struct, '/api');
        static::assertEquals($expected, json_decode($actual, true));
    }

    public function testEncodeStructWithToManyRelationships(): void
    {
        $struct = include __DIR__ . '/fixtures/testBasicWithToManyRelationships.php';
        $expected = include __DIR__ . '/fixtures/testBasicWithToManyRelationshipsExpectation.php';

        $actual = $this->encoder->encode(UserDefinition::class, $struct, '/api');

        static::assertEquals($expected, json_decode($actual, true));
    }

    public function testEncodeCollectionWithToOneRelationship(): void
    {
        $collection = include __DIR__ . '/fixtures/testCollectionWithToOneRelationship.php';
        $expected = include __DIR__ . '/fixtures/testCollectionWithToOneRelationshipExpectation.php';

        $actual = $this->encoder->encode(MediaDefinition::class, $collection, '/api');
        static::assertEquals($expected, json_decode($actual, true));
    }

    public function testEncodeStructWithSelfReference(): void
    {
        $collection = include __DIR__ . '/fixtures/testCollectionWithSelfReference.php';
        $expected = include __DIR__ . '/fixtures/testCollectionWithSelfReferenceExpectation.php';

        $actual = $this->encoder->encode(MediaFolderDefinition::class, $collection, '/api');
        static::assertEquals($expected, json_decode($actual, true));
    }

    public function testEncodeMainResourceShouldNotBeInIncluded(): void
    {
        $struct = include __DIR__ . '/fixtures/testMainResourceShouldNotBeInIncluded.php';
        $expected = include __DIR__ . '/fixtures/testMainResourceShouldNotBeInIncludedExpectation.php';

        $actual = $this->encoder->encode(UserDefinition::class, $struct, '/api');

        static::assertEquals($expected, json_decode($actual, true));
    }

    public function testEncodePayloadShouldNotBeInIncluded(): void
    {
        $struct = include __DIR__ . '/fixtures/testPayloadShouldNotBeInIncluded.php';
        $expected = include __DIR__ . '/fixtures/testPayloadShouldNotBeInIncludedExpectation.php';

        $actual = $this->encoder->encode(RuleDefinition::class, $struct, '/api');

        static::assertEquals($expected, json_decode($actual, true));
    }
}
