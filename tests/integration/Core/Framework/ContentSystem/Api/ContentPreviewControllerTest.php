<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class ContentPreviewControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const PREVIEW_URL = '/api/_action/content-system/preview/entity';

    private const PREVIEW_URL_URL = '/api/_action/content-system/preview/entity/url';

    #[TestDox('renders a draft against a real entity and returns the rendered body the full format now produces')]
    public function testPreviewReturnsTheRenderedBody(): void
    {
        $categoryId = Uuid::randomHex();
        static::getContainer()->get('category.repository')->create([[
            'id' => $categoryId,
            'name' => 'Preview category',
            'active' => true,
        ]], Context::createDefaultContext());

        $registered = static::getContainer()->get(ContentSystemElementTypeRegistry::class)->all();
        $component = array_key_first($registered);
        static::assertIsString($component);

        $this->getBrowser()->jsonRequest('POST', self::PREVIEW_URL, [
            'layout' => [[
                'id' => 'el-1',
                'component' => $component,
                'properties' => [],
                'style' => ['col-span' => ['xs' => 6]],
            ]],
            'entityType' => 'category',
            'entityId' => $categoryId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);

        // The preview route is served by the full-format factory, so it carries the same wire shape: the page
        // triple under its unprefixed names, and an element alias on the node itself.
        static::assertSame('preview', $body['name'] ?? null);
        static::assertArrayNotHasKey('layoutName', $body);
        static::assertSame('content_page', $body['apiAlias'] ?? null);

        static::assertIsArray($body['elements'] ?? null);
        static::assertCount(1, $body['elements']);
        static::assertSame('el-1', $body['elements'][0]['id'] ?? null);
        static::assertSame($component, $body['elements'][0]['component'] ?? null);
        static::assertSame('content_element', $body['elements'][0]['apiAlias'] ?? null);
        static::assertSame(['col-span' => ['xs' => 6]], $body['elements'][0]['style'] ?? null);
        static::assertArrayNotHasKey('dataRequirements', $body['elements'][0]);
    }

    #[TestDox('rejects an envelope missing a required field with 400 (not Symfony default 422)')]
    public function testPreviewReturns400ForMissingRequiredField(): void
    {
        $this->getBrowser()->jsonRequest('POST', self::PREVIEW_URL, [
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

    #[TestDox('resolves a real entity type and rejects an unregistered component with 400')]
    public function testPreviewReturns400ForUnregisteredComponent(): void
    {
        // entityType "product" matches the real, DI-wired ProductSpecificationSource, so context
        // synthesis and assignment-free resolution succeed; validation then rejects the unregistered
        // component before hydration. Exercises the real resolution success path end-to-end.
        $this->getBrowser()->jsonRequest('POST', self::PREVIEW_URL, [
            'layout' => [['id' => 'el-1', 'component' => 'Sw:Test:PreviewProbe']],
            'entityType' => 'product',
            'entityId' => 'some-product-id',
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
        ]);

        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('CONTENT_SYSTEM__ELEMENT_TYPES_INVALID', (string) $response->getContent());
    }

    #[TestDox('rejects a draft carrying an unregistered style option with 400')]
    public function testPreviewReturns400ForUnknownStyleOption(): void
    {
        $registered = static::getContainer()->get(ContentSystemElementTypeRegistry::class)->all();
        $component = array_key_first($registered);
        static::assertIsString($component);

        $this->getBrowser()->jsonRequest('POST', self::PREVIEW_URL, [
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

    #[TestDox('rejects an unknown entity type with 400')]
    public function testPreviewReturns400ForUnknownEntityType(): void
    {
        $this->getBrowser()->jsonRequest('POST', self::PREVIEW_URL, [
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
