<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class ContentPreviewControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const PREVIEW_URL_URL = '/api/_action/content-system/preview/entity/url';

    #[TestDox('previewUrl rejects a numeric wiring key with 400 invalidLayoutStructure')]
    public function testPreviewUrlReturns400ForNumericWiringKey(): void
    {
        $registered = static::getContainer()->get(ContentSystemElementTypeRegistry::class)->all();
        $component = array_key_first($registered);
        static::assertIsString($component);

        $this->getBrowser()->jsonRequest('POST', self::PREVIEW_URL_URL, [
            'layout' => [[
                'id' => 'el-1',
                'component' => $component,
                'properties' => [1 => 'x'],
            ]],
            'entityType' => 'product',
            'entityId' => 'some-product-id',
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
        ]);

        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::INVALID_LAYOUT_STRUCTURE, array_column($body['errors'], 'code'));
        // ContentPreviewController::previewUrl calls build() then store() with no branch between them, and
        // build() decodes the layout in ContentPreviewPageBuilder::build() before anything else runs — a
        // numeric wiring key throws there, so a 400 carrying no "url" is the observable signature that
        // decode ran and store never did.
        // Residual: this does not observe the payload store directly, so a future reordering that stored
        // before decoding would not be caught by this test.
        static::assertStringNotContainsString('"url"', (string) $response->getContent());
    }

    /**
     * PHP casts the JSON member name `"0"` to an integer array key, which the stored envelope cannot carry.
     * Without the DTO constraint the mint succeeds and the failure surfaces only on redemption.
     *
     * The layout uses a REGISTERED component on purpose: an unregistered one is itself a 400, which would
     * leave the status and the no-token assertions passing with the constraint removed.
     */
    #[TestDox('previewUrl rejects a numeric queryParameters key with 400 and mints no token')]
    public function testPreviewUrlReturns400ForNumericQueryParameterKey(): void
    {
        $registered = static::getContainer()->get(ContentSystemElementTypeRegistry::class)->all();
        $component = array_key_first($registered);
        static::assertIsString($component);

        $this->getBrowser()->jsonRequest('POST', self::PREVIEW_URL_URL, [
            'layout' => [['id' => 'el-1', 'component' => $component]],
            'entityType' => 'product',
            'entityId' => 'some-product-id',
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'queryParameters' => ['0' => 'x'],
        ]);

        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        // The body JSON-escapes the quotes around the key, so the message is compared after decoding.
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);
        static::assertSame('Query parameter name "0" must be a string.', $body['errors'][0]['detail'] ?? null);
        static::assertStringNotContainsString('"url"', (string) $response->getContent());
    }

    #[TestDox('previewUrl rejects an unregistered component with 400 and mints no token')]
    public function testPreviewUrlReturns400ForUnregisteredComponent(): void
    {
        $this->getBrowser()->jsonRequest('POST', self::PREVIEW_URL_URL, [
            'layout' => [['id' => 'el-1', 'component' => 'Sw:Test:PreviewProbe']],
            'entityType' => 'product',
            'entityId' => 'some-product-id',
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
        ]);

        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('CONTENT_SYSTEM__ELEMENT_TYPES_INVALID', (string) $response->getContent());
        static::assertStringNotContainsString('"url"', (string) $response->getContent());
    }

    /**
     * The 400 comes from `validationFailedStatusCode` on the action's `#[MapRequestPayload]`; the attribute
     * defaults to 422, so this case is what keeps the documented status code from drifting.
     */
    #[TestDox('previewUrl rejects a payload missing a required field with 400 rather than 422')]
    public function testPreviewUrlReturns400ForMissingRequiredField(): void
    {
        $this->getBrowser()->jsonRequest('POST', self::PREVIEW_URL_URL, [
            // entityType deliberately omitted
            'layout' => [['id' => 'el-1', 'component' => 'Sw:Test:PreviewProbe']],
            'entityId' => 'does-not-matter',
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
        ]);

        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);
        static::assertArrayHasKey('errors', $body);
    }

    #[TestDox('previewUrl rejects a draft carrying an unregistered style option with 400')]
    public function testPreviewUrlReturns400ForUnknownStyleOption(): void
    {
        $registered = static::getContainer()->get(ContentSystemElementTypeRegistry::class)->all();
        $component = array_key_first($registered);
        static::assertIsString($component);

        $this->getBrowser()->jsonRequest('POST', self::PREVIEW_URL_URL, [
            'layout' => [[
                'id' => 'el-1',
                'component' => $component,
                'properties' => [],
                'style' => ['definitely-not-a-style-option' => ['xs' => 'x']],
            ]],
            'entityType' => 'product',
            'entityId' => 'some-product-id',
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
        ]);

        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('CONTENT_SYSTEM__ELEMENT_TYPES_INVALID', (string) $response->getContent());
        static::assertStringContainsString('definitely-not-a-style-option', (string) $response->getContent());
    }

    /**
     * The refusal runs ahead of the build gate, so the envelope below is deliberately one the builder would
     * itself reject: a 400 naming the parameter rather than the component is what shows the order.
     */
    #[TestDox('previewUrl rejects a query field selection with 400 field_selection_not_supported')]
    public function testPreviewUrlReturns400ForFieldSelection(): void
    {
        $this->getBrowser()->jsonRequest('POST', self::PREVIEW_URL_URL . '?includes[content_page][]=id', [
            'layout' => [['id' => 'el-1', 'component' => 'Sw:Test:PreviewProbe']],
            'entityType' => 'product',
            'entityId' => 'some-product-id',
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
        ]);

        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);
        static::assertSame(
            [ContentSystemException::FIELD_SELECTION_NOT_SUPPORTED],
            array_column($body['errors'], 'code'),
        );
        static::assertStringContainsString('includes', (string) $response->getContent());
        static::assertStringNotContainsString('"url"', (string) $response->getContent());
    }

    #[TestDox('previewUrl rejects an unknown entity type with 400')]
    public function testPreviewUrlReturns400ForUnknownEntityType(): void
    {
        $this->getBrowser()->jsonRequest('POST', self::PREVIEW_URL_URL, [
            'layout' => [['id' => 'el-1', 'component' => 'Sw:Test:PreviewProbe']],
            'entityType' => 'not_a_real_entity_type',
            'entityId' => 'some-id',
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
        ]);

        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('CONTENT_SYSTEM__UNKNOWN_ENTITY_TYPE', (string) $response->getContent());
    }
}
