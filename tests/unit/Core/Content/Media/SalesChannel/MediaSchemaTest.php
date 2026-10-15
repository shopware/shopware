<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\SalesChannel;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApiFileLoader;
use Shopware\Core\Framework\Log\Package;

/**
 * Validates Store API media responses against the JSON-owned `Media` and `MediaThumbnail` schema components.
 * The payloads are copies of what `POST /store-api/media` returns; {@see \Shopware\Tests\Integration\Core\Content\Media\SalesChannel\MediaRouteTest}
 * keeps the live response and the schema from drifting apart.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversNothing]
class MediaSchemaTest extends TestCase
{
    private const SCHEMA_ID = 'https://shopware.test/store-api.json';

    /**
     * @param array<string, mixed> $media
     */
    #[DataProvider('mediaProvider')]
    public function testMediaSchemaAcceptsTheResponse(array $media): void
    {
        $payload = json_decode((string) json_encode($media), false, 512, \JSON_THROW_ON_ERROR);

        $error = self::getSchemaValidator()->validate($payload, self::SCHEMA_ID . '#/components/schemas/Media')->error();

        static::assertNull(
            $error,
            $error === null ? '' : 'The media response does not match the Media schema: ' . json_encode((new ErrorFormatter())->format($error, false), \JSON_PRETTY_PRINT)
        );
    }

    /**
     * @return \Generator<string, array{array<string, mixed>}>
     */
    public static function mediaProvider(): \Generator
    {
        $uploaded = [
            'translated' => ['alt' => null, 'title' => null, 'customFields' => []],
            'createdAt' => '2026-10-12T13:36:45.170+00:00',
            'updatedAt' => null,
            'mimeType' => 'image/jpeg',
            'fileExtension' => 'jpg',
            'fileSize' => 12345,
            'title' => null,
            'metaData' => ['hash' => 'f4f4cf2d62e23762156532d937e8e10f', 'type' => 2, 'width' => 800, 'height' => 600],
            'uploadedAt' => '2026-10-12T13:36:42.917+00:00',
            'alt' => null,
            'url' => 'http://localhost:8000/media/aa/bb/terms.jpg?ts=1791812205',
            'fileName' => 'terms',
            'translations' => null,
            'thumbnails' => [[
                'translated' => [],
                'createdAt' => '2026-10-12T13:36:45.170+00:00',
                'updatedAt' => null,
                'path' => 'thumbnail/aa/bb/terms_400x300.jpg',
                'width' => 400,
                'height' => 300,
                'url' => 'http://localhost:8000/thumbnail/aa/bb/terms_400x300.jpg?ts=1791812205',
                'mediaId' => '01a130547a6d72468cba76b4f535eceb',
                'mediaThumbnailSizeId' => '01a130547a707305a7f40151990aab3e',
                'customFields' => null,
                'id' => '01a130547a6f73fdbe7543570bd6357a',
                'apiAlias' => 'media_thumbnail',
            ]],
            'hasFile' => true,
            'private' => false,
            'path' => 'media/aa/bb/terms.jpg',
            'config' => null,
            'customFields' => null,
            'id' => '01a130547a6d72468cba76b4f535eceb',
            'apiAlias' => 'media',
        ];

        yield 'uploaded media without alt, title, config or custom fields' => [$uploaded];

        yield 'uploaded media with alt, title, config and custom fields' => [array_merge($uploaded, [
            'translated' => ['alt' => 'Guarantee terms', 'title' => 'Terms', 'customFields' => ['source' => 'test']],
            'title' => 'Terms',
            'alt' => 'Guarantee terms',
            'thumbnails' => [],
            'config' => ['focus' => 'center'],
            'customFields' => ['source' => 'test'],
            'id' => '01a130547b1c71ffa4050455ee97cd7d',
        ])];

        yield 'media entry before its file is uploaded' => [[
            'translated' => ['alt' => null, 'title' => null, 'customFields' => []],
            'createdAt' => '2026-10-12T13:36:43.046+00:00',
            'updatedAt' => null,
            'mimeType' => null,
            'fileExtension' => null,
            'fileSize' => null,
            'title' => null,
            'metaData' => null,
            'uploadedAt' => null,
            'alt' => null,
            'url' => '',
            'fileName' => null,
            'translations' => null,
            'thumbnails' => [],
            'hasFile' => false,
            'private' => false,
            'path' => null,
            'config' => null,
            'customFields' => null,
            'id' => '01a1305471f27268af38ae2c8369986c',
            'apiAlias' => 'media',
        ]];
    }

    private static function getSchemaValidator(): Validator
    {
        $schemaDir = \dirname(__DIR__, 6) . '/src/Core/Framework/Api/ApiDefinition/Generator/Schema/StoreApi';
        $spec = (new OpenApiFileLoader([$schemaDir]))->loadOpenapiSpecification();

        $validator = new Validator();
        $validator->setMaxErrors(20);
        $validator->resolver()?->registerRaw(json_decode((string) json_encode($spec), false, 512, \JSON_THROW_ON_ERROR), self::SCHEMA_ID);

        return $validator;
    }
}
