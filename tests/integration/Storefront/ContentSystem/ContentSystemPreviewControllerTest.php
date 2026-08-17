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

    #[TestDox('answers 400 for a stored envelope that does not decode into a preview request')]
    public function testUndecodableStoredEnvelopeIsBadRequest(): void
    {
        $token = Uuid::randomHex();
        $cache = static::getContainer()->get('cache.system');
        $item = $cache->getItem('content-system.preview.' . $token);
        // An envelope with no entityType: the redemption route refuses it instead of previewing an empty entity.
        $item->set(['layout' => [], 'salesChannelId' => $this->getSalesChannelId()]);
        $cache->save($item);

        $response = $this->request('GET', 'content-system/preview/' . $token, []);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[TestDox('renders the draft layout for a valid stored envelope')]
    public function testValidStoredEnvelopeRenders(): void
    {
        $productId = $this->createProduct();

        $store = static::getContainer()->get(ContentPreviewPayloadStore::class);
        $token = $store->store(new ContentPreviewRequest(
            layout: [[
                'id' => Uuid::randomHex(),
                'component' => 'Sw:Content:Text',
                'properties' => ['text' => '<p>Preview body</p>'],
            ]],
            entityType: 'product',
            entityId: $productId,
            salesChannelId: $this->getSalesChannelId(),
        ));

        $response = $this->request('GET', 'content-system/preview/' . $token, []);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('Preview body', (string) $response->getContent());
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
            'manufacturer' => ['name' => 'test'],
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
