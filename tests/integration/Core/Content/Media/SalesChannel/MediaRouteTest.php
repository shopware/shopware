<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\SalesChannel;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
class MediaRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private const SCHEMA_ID = 'https://shopware.test/store-api.json';

    /**
     * @param array<string, mixed> $media
     */
    #[DataProvider('mediaProvider')]
    public function testResponseMatchesTheStoreApiSchema(array $media): void
    {
        $media['id'] = Uuid::randomHex();

        // uploadedAt, fileSize, metaData and the thumbnail sizes are written by the upload
        Context::createDefaultContext()->scope(Context::SYSTEM_SCOPE, static function (Context $context) use ($media): void {
            static::getContainer()->get('media.repository')->create([$media], $context);
        });

        $browser = $this->createCustomSalesChannelBrowser(['id' => Uuid::randomHex()]);
        $browser->request('POST', '/store-api/media', ['ids' => [$media['id']]]);

        $content = (string) $browser->getResponse()->getContent();
        $response = json_decode($content, false, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($response, $content);
        static::assertCount(1, $response, $content);

        $error = $this->getSchemaValidator()->validate($response[0], self::SCHEMA_ID . '#/components/schemas/Media')->error();

        static::assertNull(
            $error,
            $error === null ? '' : 'The Store API media response does not match the Media schema: ' . json_encode((new ErrorFormatter())->format($error, false), \JSON_PRETTY_PRINT)
        );
    }

    public static function mediaProvider(): \Generator
    {
        yield 'uploaded media without alt, title, config or custom fields' => [[
            'fileName' => 'terms',
            'fileExtension' => 'jpg',
            'mimeType' => 'image/jpeg',
            'path' => 'media/aa/bb/terms.jpg',
            'uploadedAt' => new \DateTimeImmutable(),
            'fileSize' => 12345,
            'metaData' => ['hash' => 'f4f4cf2d62e23762156532d937e8e10f', 'type' => 2, 'width' => 800, 'height' => 600],
            'thumbnails' => [[
                'width' => 400,
                'height' => 300,
                'path' => 'thumbnail/aa/bb/terms_400x300.jpg',
                'mediaThumbnailSize' => ['width' => 401, 'height' => 301],
            ]],
        ]];

        yield 'uploaded media with alt, title, config and custom fields' => [[
            'fileName' => 'terms',
            'fileExtension' => 'jpg',
            'mimeType' => 'image/jpeg',
            'path' => 'media/aa/bb/terms.jpg',
            'uploadedAt' => new \DateTimeImmutable(),
            'fileSize' => 12345,
            'metaData' => ['hash' => 'f4f4cf2d62e23762156532d937e8e10f', 'type' => 2, 'width' => 800, 'height' => 600],
            'alt' => 'Guarantee terms',
            'title' => 'Terms',
            'config' => ['focus' => 'center'],
            'customFields' => ['source' => 'test'],
        ]];

        yield 'media entry before its file is uploaded' => [[]];
    }

    private function getSchemaValidator(): Validator
    {
        $spec = static::getContainer()->get(DefinitionService::class)->generate(
            'openapi-3',
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON
        );

        $validator = new Validator();
        $validator->setMaxErrors(20);
        $validator->resolver()?->registerRaw(json_decode((string) json_encode($spec), false, 512, \JSON_THROW_ON_ERROR), self::SCHEMA_ID);

        return $validator;
    }
}
