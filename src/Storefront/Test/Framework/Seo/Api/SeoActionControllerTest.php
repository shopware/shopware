<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Seo\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\Exception\SeoUrlRouteNotFoundException;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\Seo\StorefrontSalesChannelTestHelper;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;

/**
 * @internal
 */
class SeoActionControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    protected function setUp(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM `order`');
        $connection->executeStatement('DELETE FROM customer');
        $connection->executeStatement('DELETE FROM product');
        $connection->executeStatement('DELETE FROM sales_channel');
    }

    public function testValidateEmpty(): void
    {
        $this->getBrowser()->request('POST', '/api/_action/seo-url-template/validate');
        $response = $this->getBrowser()->getResponse();
        $result = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotEmpty($result['errors']);
        static::assertEquals(400, $response->getStatusCode());
    }

    public function testValidateInvalid(): void
    {
        $template = new SeoUrlTemplateEntity();
        $template->setRouteName('frontend.detail.page');
        $template->setTemplate('{{ product.name }');
        $template->setEntityName($this->getContainer()->get(ProductDefinition::class)->getEntityName());
        $template->setSalesChannelId(TestDefaults::SALES_CHANNEL);

        $this->getBrowser()->request('POST', '/api/_action/seo-url-template/validate', $template->jsonSerialize());
        $response = $this->getBrowser()->getResponse();
        $result = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotEmpty($result['errors'] ?? []);
        static::assertEquals(400, $response->getStatusCode());
    }

    public function testValidateValid(): void
    {
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $this->createTestProduct($salesChannelId);
        $template = new SeoUrlTemplateEntity();
        $template->setRouteName('frontend.detail.page');
        $template->setTemplate('{{ product.name }}');
        $template->setEntityName(ProductDefinition::ENTITY_NAME);
        $template->setSalesChannelId($salesChannelId);

        $this->getBrowser()->request('POST', '/api/_action/seo-url-template/validate', $template->jsonSerialize());
        $response = $this->getBrowser()->getResponse();
        $result = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayNotHasKey('errors', $result);
        static::assertEquals(200, $response->getStatusCode());
    }

    public function testGetSeoContext(): void
    {
        $product = [
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'name' => 'test',
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 10,
                    'net' => 20,
                    'linked' => false,
                ],
            ],
            'manufacturer' => [
                'id' => Uuid::randomHex(),
                'name' => 'test',
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'stock' => 0,
        ];
        $this->getBrowser()->request('POST', '/api/product', [], [], [], json_encode($product, \JSON_THROW_ON_ERROR));

        $data = [
            'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
            'entityName' => $this->getContainer()->get(ProductDefinition::class)->getEntityName(),
        ];
        $this->getBrowser()->request('POST', '/api/_action/seo-url-template/context', [], [], [], json_encode($data, \JSON_THROW_ON_ERROR));

        $response = $this->getBrowser()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertNotNull($data['product'] ?? null);
    }

    public function testPreview(): void
    {
        $this->createStorefrontSalesChannelContext(TestDefaults::SALES_CHANNEL, 'test');
        $this->createTestProduct();

        $data = [
            'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
            'entityName' => $this->getContainer()->get(ProductDefinition::class)->getEntityName(),
            'template' => '{{ product.name }}',
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
        ];
        $this->getBrowser()->request('POST', '/api/_action/seo-url-template/preview', $data);

        $response = $this->getBrowser()->getResponse();

        static::assertEquals(200, $response->getStatusCode(), $response->getContent());
        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals('test', $data[0]['seoPathInfo']);
    }

    public function testPreviewWithSalesChannel(): void
    {
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $aId = $this->createTestCategory('A');
        $this->createTestCategory('B', $aId);

        $this->updateSalesChannelNavigationEntryPoint($salesChannelId, $aId);

        $data = [
            'routeName' => NavigationPageSeoUrlRoute::ROUTE_NAME,
            'entityName' => $this->getContainer()->get(CategoryDefinition::class)->getEntityName(),
            'template' => NavigationPageSeoUrlRoute::DEFAULT_TEMPLATE,
            'salesChannelId' => $salesChannelId,
        ];
        $this->getBrowser()->request('POST', '/api/_action/seo-url-template/preview', $data);

        $response = $this->getBrowser()->getResponse();
        static::assertEquals(200, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $urls = array_column($data, 'seoPathInfo');
        static::assertContains('B/', $urls);
    }

    public function testUnknownRoute(): void
    {
        $template = new SeoUrlTemplateEntity();
        $template->setRouteName('unknown.route');
        $template->setTemplate('{{ product.name }}');
        $template->setEntityName($this->getContainer()->get(ProductDefinition::class)->getEntityName());
        $template->setSalesChannelId(TestDefaults::SALES_CHANNEL);

        $this->getBrowser()->request('POST', '/api/_action/seo-url-template/validate', $template->jsonSerialize());
        $response = $this->getBrowser()->getResponse();
        $result = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $result);
        static::assertEquals(404, $response->getStatusCode());

        static::assertEquals(SeoUrlRouteNotFoundException::ERROR_CODE, $result['errors'][0]['code']);
    }

    public function testUpdateDefaultCanonical(): void
    {
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $id = $this->createTestProduct($salesChannelId);

        $seoUrls = $this->getSeoUrls($id, true, $salesChannelId);
        static::assertCount(1, $seoUrls);

        $seoUrl = $seoUrls[0]['attributes'];
        static::assertFalse($seoUrl['isModified']);

        $newSeoPathInfo = 'my-awesome-seo-path';
        $seoUrl['seoPathInfo'] = $newSeoPathInfo;
        $seoUrl['isModified'] = true;

        // modify canonical
        $this->getBrowser()->request('PATCH', '/api/_action/seo-url/canonical', $seoUrl);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(204, $response->getStatusCode(), $response->getContent());

        $seoUrls = $this->getSeoUrls($id, true, $salesChannelId);
        static::assertCount(1, $seoUrls);
        $seoUrl = $seoUrls[0]['attributes'];
        static::assertTrue($seoUrl['isModified']);
        static::assertEquals($newSeoPathInfo, $seoUrl['seoPathInfo']);

        $productUpdate = [
            'id' => $id,
            'name' => 'unused name',
        ];
        $this->getBrowser()->request('PATCH', '/api/product/' . $id, $productUpdate);

        // seo url is not updated with the product
        $seoUrls = $this->getSeoUrls($id, true, $salesChannelId);
        static::assertCount(1, $seoUrls);
        $seoUrl = $seoUrls[0]['attributes'];
        static::assertTrue($seoUrl['isModified']);
        static::assertEquals($newSeoPathInfo, $seoUrl['seoPathInfo']);
    }

    public function testUpdateCanonicalWithCustomSalesChannel(): void
    {
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $id = $this->createTestProduct($salesChannelId);

        $seoUrls = $this->getSeoUrls($id, true, $salesChannelId);
        static::assertCount(1, $seoUrls);

        $seoUrl = $seoUrls[0]['attributes'];
        static::assertFalse($seoUrl['isModified']);

        $newSeoPathInfo = 'my-awesome-seo-path';
        $seoUrl['seoPathInfo'] = $newSeoPathInfo;
        $seoUrl['isModified'] = true;
        $seoUrl['salesChannelId'] = $salesChannelId;

        // modify canonical
        $this->getBrowser()->request('PATCH', '/api/_action/seo-url/canonical', $seoUrl);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(204, $response->getStatusCode(), $response->getContent());

        $seoUrls = $this->getSeoUrls($id, true, $salesChannelId);
        static::assertCount(1, $seoUrls);
        $seoUrl = $seoUrls[0]['attributes'];
        static::assertTrue($seoUrl['isModified']);
        static::assertEquals($newSeoPathInfo, $seoUrl['seoPathInfo']);

        $newProductNumber = Uuid::randomHex();
        $productUpdate = [
            'id' => $id,
            'name' => 'updated-name',
            'productNumber' => $newProductNumber,
        ];
        $this->getBrowser()->request('PATCH', '/api/product/' . $id, $productUpdate);

        // seoPathInfo for the custom sales_channel is not updated with the product
        $seoUrls = $this->getSeoUrls($id, true, $salesChannelId);
        static::assertCount(1, $seoUrls);
        $seoUrl = $seoUrls[0]['attributes'];
        static::assertTrue($seoUrl['isModified']);
        static::assertEquals($newSeoPathInfo, $seoUrl['seoPathInfo']);
    }

    private function getSeoUrls(string $id, ?bool $canonical = null, ?string $salesChannelId = null): array
    {
        $params = [];
        if ($canonical !== null) {
            $params = [
                'filter' => [
                    'isCanonical' => $canonical,
                    'salesChannelId' => $salesChannelId,
                ],
            ];
        }
        $this->getBrowser()->request('GET', '/api/product/' . $id . '/seoUrls', $params);
        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());

        $content = $this->getBrowser()->getResponse()->getContent();

        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['data'];
    }

    private function createTestProduct(string $salesChannelId = TestDefaults::SALES_CHANNEL): string
    {
        $id = Uuid::randomHex();
        $product = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'name' => 'test',
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 10,
                    'net' => 20,
                    'linked' => false,
                ],
            ],
            'manufacturer' => [
                'id' => Uuid::randomHex(),
                'name' => 'test',
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'stock' => 0,
            'visibilities' => [
                [
                    'salesChannelId' => $salesChannelId,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];
        $this->getBrowser()->request('POST', '/api/product', [], [], [], json_encode($product, \JSON_THROW_ON_ERROR));

        return $id;
    }

    private function createTestCategory(string $name, ?string $parentId = null): string
    {
        $id = Uuid::randomHex();
        $product = [
            'id' => $id,
            'name' => $name,
            'parentId' => $parentId,
        ];
        $this->getBrowser()->request('POST', '/api/category', [], [], [], json_encode($product, \JSON_THROW_ON_ERROR));

        return $id;
    }
}
