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
                    'properties' => ['text' => '<p>Preview body</p>'],
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
