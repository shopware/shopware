<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\ContentSystem;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewPayloadStore;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewRequest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class ContentSystemPreviewControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    #[TestDox('answers 404 for a token that addresses no stored envelope')]
    public function testUnknownTokenIsNotFound(): void
    {
        $response = $this->request('GET', 'content-system/preview/no-such-token', []);

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    #[TestDox('answers 500 for a stored envelope that does not decode into a preview request')]
    public function testUndecodableStoredEnvelopeIsServerError(): void
    {
        $token = Uuid::randomHex();
        $cache = static::getContainer()->get('cache.system');
        $item = $cache->getItem('content-system.preview.' . $token);
        // An envelope with no entityType: only a validated envelope is ever written, so a malformed hit is
        // server-side state, not a defect in this caller's request.
        $item->set(['layout' => [], 'salesChannelId' => $this->getSalesChannelId()]);
        $cache->save($item);

        try {
            $response = $this->request('GET', 'content-system/preview/' . $token, []);

            static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        } finally {
            $cache->deleteItem('content-system.preview.' . $token);
        }
    }

    /**
     * A status assertion alone proves nothing here: `strict_variables` is false, so a page or element member
     * the Twig components stop resolving yields null, the element loop coerces to empty, and the preview
     * renders a blank page with HTTP 200. The layout is therefore nested and styled so that one render
     * exercises every member the components read off the two types: `page.id` and `page.elements` on the
     * page, and `id`, `component`, `properties`, `slots` and `style.values` on the elements.
     */
    #[TestDox('renders a nested styled draft layout, resolving every page and element member the components read')]
    public function testValidStoredEnvelopeRenders(): void
    {
        $productId = $this->createProduct();
        $containerId = Uuid::randomHex();
        $textId = Uuid::randomHex();
        $manufacturerId = Uuid::randomHex();

        $store = static::getContainer()->get(ContentPreviewPayloadStore::class);
        $token = $store->store(new ContentPreviewRequest(
            layout: [[
                'id' => $containerId,
                'component' => 'Sw:Grid:Container',
                'properties' => [],
                'style' => ['col-span' => ['md' => 6]],
                'slots' => ['content' => [[
                    'id' => $textId,
                    'component' => 'Sw:Content:Text',
                    'properties' => ['text' => [Defaults::LANGUAGE_SYSTEM => '<p>Preview body</p>']],
                ], [
                    'id' => $manufacturerId,
                    'component' => 'Sw:Product:Manufacturer',
                    'properties' => [],
                ]]],
            ]],
            entityType: 'product',
            entityId: $productId,
            salesChannelId: $this->getSalesChannelId(),
        ));

        $response = $this->request('GET', 'content-system/preview/' . $token, []);
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);

        // An unresolved `page.id` renders as a bare valueless `data-page-id`, so the `="…"` is the assertion.
        static::assertMatchesRegularExpression(
            '/data-page-id="[0-9a-f]{32}"/',
            $content,
            'The preview layout id must resolve onto the page wrapper.'
        );
        // `strict_variables` is false, so a Twig member that stops resolving yields null instead of throwing,
        // and the content region renders empty with the page chrome still producing a non-empty HTTP 200 body.
        // Asserting this concrete element id is what actually catches a blank content region; a non-empty body
        // check would not, because the surrounding page chrome renders regardless.
        // `page.elements` and the root element's `id`.
        static::assertStringContainsString('data-element-id="' . $containerId . '"', $content);
        // `element.style.values`, which resolves through a getter because the property is private.
        static::assertStringContainsString('col-span-md-6', $content);
        // `element.slots`, carried into the container component and read back by the Slot component.
        static::assertStringContainsString('data-element-id="' . $textId . '"', $content);
        // `element.component` picked the Text component and `element.properties` fed it.
        static::assertStringContainsString('Preview body', $content);
        // The shared element renderer marks preview components without making the manufacturer component
        // depend on the request global itself.
        static::assertStringContainsString('data-element-id="' . $manufacturerId . '"', $content);
        static::assertStringContainsString('Manufacturer not available', $content);
    }

    /**
     * The envelope's `languageId` is what `ContentPreviewPageBuilder::build()` hands the context service, so it
     * decides the language chain reduction runs against — the layout translation as well as the entity data
     * language.
     *
     * The map carries three entries and the requested one sits in the middle, so the requested copy can only
     * be reached by walking the chain: a selection that took the map's first entry would serve the anchor, and
     * one that took its last would serve the unreachable entry. Draft maps travel through the payload store
     * rather than a JSON column, so the authored order really is the order reduction sees.
     */
    #[TestDox('serves the layout translation the preview request languageId names')]
    public function testPreviewLanguageIdSelectsTheLayoutTranslation(): void
    {
        $productId = $this->createProduct();
        $languageId = $this->createSalesChannelLanguage();
        $textId = Uuid::randomHex();

        $store = static::getContainer()->get(ContentPreviewPayloadStore::class);
        $token = $store->store(new ContentPreviewRequest(
            layout: [[
                'id' => $textId,
                'component' => 'Sw:Content:Text',
                'properties' => ['text' => [
                    Defaults::LANGUAGE_SYSTEM => '<p>Anchor copy</p>',
                    $languageId => '<p>Requested language copy</p>',
                    // A language id no `language` row carries, so it is on no chain and reachable by nothing.
                    Uuid::randomHex() => '<p>Unreachable copy</p>',
                ]],
            ]],
            entityType: 'product',
            entityId: $productId,
            salesChannelId: $this->getSalesChannelId(),
            languageId: $languageId,
        ));

        $response = $this->request('GET', 'content-system/preview/' . $token, []);
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        // The element really rendered, so the copy assertions below are about which entry was picked rather
        // than about a blank content region.
        static::assertStringContainsString('data-element-id="' . $textId . '"', $content);
        static::assertStringContainsString('Requested language copy', $content);
        static::assertStringNotContainsString('Anchor copy', $content);
        static::assertStringNotContainsString('Unreachable copy', $content);
    }

    /**
     * A child of the system language, added to the sales channel the preview renders against: the context
     * factory refuses a language outside `sales_channel_language` before any content code runs. The
     * many-to-many write adds the mapping row rather than replacing the existing languages.
     */
    private function createSalesChannelLanguage(): string
    {
        $languageId = Uuid::randomHex();
        $localeId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        static::getContainer()->get('language.repository')->create([[
            'id' => $languageId,
            'name' => 'Preview language',
            'parentId' => Defaults::LANGUAGE_SYSTEM,
            'active' => true,
            'locale' => [
                'id' => $localeId,
                'name' => 'Preview language',
                'territory' => 'Preview territory',
                // The language and region subtags must be real ISO codes; only the trailing subtag is free.
                'code' => 'de-CH-preview',
            ],
            'translationCodeId' => $localeId,
        ]], $context);

        static::getContainer()->get('sales_channel.repository')->update([[
            'id' => $this->getSalesChannelId(),
            'languages' => [['id' => $languageId]],
        ]], $context);

        return $languageId;
    }

    private function createProduct(): string
    {
        $id = Uuid::randomHex();

        $salesChannelIds = static::getContainer()->get(Connection::class)
            ->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM sales_channel');

        static::getContainer()->get('product.repository')->create([[
            'id' => $id,
            'productNumber' => $id,
            'stock' => 5,
            'name' => 'Preview product',
            'isCloseout' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 19],
            'visibilities' => array_map(
                static fn (string $salesChannelId): array => [
                    'salesChannelId' => $salesChannelId,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
                $salesChannelIds,
            ),
        ]], Context::createDefaultContext());

        return $id;
    }
}
