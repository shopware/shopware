<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\SalesChannel;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Guards the one thing the unit test cannot: that the live route response still has the shape the schema describes.
 * The nullable field combinations are covered by {@see \Shopware\Tests\Unit\Core\Content\Media\SalesChannel\MediaSchemaTest}.
 *
 * @internal
 */
#[Package('discovery')]
class MediaRouteTest extends TestCase
{
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private const SCHEMA_ID = 'https://shopware.test/store-api.json';

    public function testResponseMatchesTheStoreApiSchema(): void
    {
        $mediaId = Uuid::randomHex();

        // a media entry before its file is uploaded answers with every file field set to null
        Context::createDefaultContext()->scope(Context::SYSTEM_SCOPE, static function (Context $context) use ($mediaId): void {
            static::getContainer()->get('media.repository')->create([['id' => $mediaId]], $context);
        });

        $browser = $this->createCustomSalesChannelBrowser(['id' => Uuid::randomHex()]);
        $browser->request('POST', '/store-api/media', ['ids' => [$mediaId]]);

        $content = (string) $browser->getResponse()->getContent();
        $response = json_decode($content, false, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($response, $content);
        static::assertCount(1, $response, $content);

        $spec = static::getContainer()->get(DefinitionService::class)->generate('openapi-3', DefinitionService::STORE_API, DefinitionService::TYPE_JSON);
        $validator = new Validator();
        $validator->setMaxErrors(20);
        $validator->resolver()?->registerRaw(json_decode((string) json_encode($spec), false, 512, \JSON_THROW_ON_ERROR), self::SCHEMA_ID);

        $error = $validator->validate($response[0], self::SCHEMA_ID . '#/components/schemas/Media')->error();

        static::assertNull(
            $error,
            $error === null ? '' : 'The Store API media response does not match the Media schema: ' . json_encode((new ErrorFormatter())->format($error, false), \JSON_PRETTY_PRINT)
        );
    }
}
