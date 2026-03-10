<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Seo\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\Exception\SeoUrlRouteNotFoundException;
use Shopware\Core\Content\Seo\SeoException;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\Seo\StorefrontSalesChannelTestHelper;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;

/**
 * @internal
 *
 * @phpstan-type Product array{id: string, attributes: array{isModified: boolean, seoPathInfo: string } }
 */
#[Package('inventory')]
class SeoActionControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM `order`');
        $connection->executeStatement('DELETE FROM customer');
        $connection->executeStatement('DELETE FROM product');
        $connection->executeStatement('DELETE FROM sales_channel');
    }

    public function testValidateEmpty(): void
    {
        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/validate');
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);
        $result = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotEmpty($result['errors']);
        static::assertSame(400, $response->getStatusCode());
    }

    public function testValidateInvalidTwigSyntax(): void
    {
        $template = new SeoUrlTemplateEntity();
        $template->setRouteName('frontend.detail.page');
        $template->setTemplate('{{ product.name }');
        $template->setEntityName(static::getContainer()->get(ProductDefinition::class)->getEntityName());
        $template->setSalesChannelId(TestDefaults::SALES_CHANNEL);

        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/validate', $template->jsonSerialize());
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);
        $result = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotEmpty($result['errors'] ?? []);
        static::assertSame(400, $response->getStatusCode());
    }

    public function testValidateInvalidDataUsage(): void
    {
        $template = new SeoUrlTemplateEntity();
        $template->setRouteName('frontend.detail.page');
        $template->setTemplate('{{ product.undefinedProperty }}');
        $template->setEntityName(static::getContainer()->get(ProductDefinition::class)->getEntityName());
        $template->setSalesChannelId(TestDefaults::SALES_CHANNEL);

        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/validate', $template->jsonSerialize());
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);
        $result = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotEmpty($result['errors'] ?? []);
        static::assertSame(400, $response->getStatusCode());
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

        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/validate', $template->jsonSerialize());
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);
        $result = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayNotHasKey('errors', $result);
        static::assertSame(200, $response->getStatusCode());
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
        $this->getBrowser()->jsonRequest('POST', '/api/product', $product);

        $data = [
            'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
            'entityName' => static::getContainer()->get(ProductDefinition::class)->getEntityName(),
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/context', $data);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(200, $response->getStatusCode());

        $content = $response->getContent();
        static::assertIsString($content);
        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertNotNull($data['product'] ?? null);
    }

    public function testPreview(): void
    {
        $this->createStorefrontSalesChannelContext(TestDefaults::SALES_CHANNEL, 'test');
        $this->createTestProduct();

        $data = [
            'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
            'entityName' => static::getContainer()->get(ProductDefinition::class)->getEntityName(),
            'template' => '{{ product.name }}',
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/preview', $data);

        $response = $this->getBrowser()->getResponse();

        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $content = $response->getContent();
        static::assertIsString($content);
        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('test', $data[0]['seoPathInfo']);
    }

    public function testPreviewWithBrokenTemplate(): void
    {
        $this->createStorefrontSalesChannelContext(TestDefaults::SALES_CHANNEL, 'test');
        $this->createTestProduct();

        $data = [
            'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
            'entityName' => static::getContainer()->get(ProductDefinition::class)->getEntityName(),
            'template' => '{{ product.undefinedProperty }}',
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/preview', $data);

        $response = $this->getBrowser()->getResponse();

        static::assertSame(400, $response->getStatusCode(), (string) $response->getContent());
        $content = $response->getContent();
        static::assertIsString($content);
        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('FRAMEWORK__INVALID_SEO_TEMPLATE', $data['errors'][0]['code']);
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
            'entityName' => static::getContainer()->get(CategoryDefinition::class)->getEntityName(),
            'template' => NavigationPageSeoUrlRoute::DEFAULT_TEMPLATE,
            'salesChannelId' => $salesChannelId,
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/preview', $data);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $content = $response->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        $urls = array_column($data, 'seoPathInfo');
        static::assertContains('B/', $urls);
    }

    public function testUnknownRoute(): void
    {
        $template = new SeoUrlTemplateEntity();
        $template->setRouteName('unknown.route');
        $template->setTemplate('{{ product.name }}');
        $template->setEntityName(static::getContainer()->get(ProductDefinition::class)->getEntityName());
        $template->setSalesChannelId(TestDefaults::SALES_CHANNEL);

        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/validate', $template->jsonSerialize());
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);
        $result = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $result);
        static::assertSame(404, $response->getStatusCode());

        $expectedErrorCode = SeoException::SEO_URL_ROUTE_NOT_FOUND;
        if (!Feature::isActive('v6.8.0.0')) {
            $expectedErrorCode = SeoUrlRouteNotFoundException::ERROR_CODE;
        }

        static::assertSame($expectedErrorCode, $result['errors'][0]['code']);
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
        $this->getBrowser()->jsonRequest('PATCH', '/api/_action/seo-url/canonical', $seoUrl);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(204, $response->getStatusCode(), (string) $response->getContent());

        $seoUrls = $this->getSeoUrls($id, true, $salesChannelId);

        static::assertCount(1, $seoUrls);
        $seoUrl = $seoUrls[0]['attributes'];
        static::assertTrue($seoUrl['isModified']);
        static::assertSame($newSeoPathInfo, $seoUrl['seoPathInfo']);

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
        static::assertSame($newSeoPathInfo, $seoUrl['seoPathInfo']);
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
        $this->getBrowser()->jsonRequest('PATCH', '/api/_action/seo-url/canonical', $seoUrl);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(204, $response->getStatusCode(), (string) $response->getContent());

        $seoUrls = $this->getSeoUrls($id, true, $salesChannelId);
        static::assertCount(1, $seoUrls);
        $seoUrl = $seoUrls[0]['attributes'];
        static::assertTrue($seoUrl['isModified']);
        static::assertSame($newSeoPathInfo, $seoUrl['seoPathInfo']);

        $newProductNumber = Uuid::randomHex();
        $productUpdate = [
            'id' => $id,
            'name' => 'updated-name',
            'productNumber' => $newProductNumber,
        ];
        $this->getBrowser()->jsonRequest('PATCH', '/api/product/' . $id, $productUpdate);

        // seoPathInfo for the custom sales_channel is not updated with the product
        $seoUrls = $this->getSeoUrls($id, true, $salesChannelId);
        static::assertCount(1, $seoUrls);
        $seoUrl = $seoUrls[0]['attributes'];
        static::assertTrue($seoUrl['isModified']);
        static::assertSame($newSeoPathInfo, $seoUrl['seoPathInfo']);
    }

    public function testUpdateDefaultCanonicalForHeadlessBehavesCorrectly(): void
    {
        $salesChannelId = Uuid::randomHex();
        $this->createSalesChannelContext(['id' => $salesChannelId, 'typeId' => Defaults::SALES_CHANNEL_TYPE_API, 'name' => 'test']);

        $id = $this->createTestProduct($salesChannelId);

        $seoUrls = $this->getSeoUrls($id, true, $salesChannelId);

        static::assertCount(0, $seoUrls);

        $newSeoPathInfo = 'my-awesome-seo-path';
        $seoUrl = [
            'foreignKey' => $id,
            'seoPathInfo' => $newSeoPathInfo,
            'pathInfo' => '/detail/' . $id,
            'salesChannelId' => $salesChannelId,
            'isModified' => true,
            'routeName' => 'frontend.detail.page',
        ];

        // modify canonical
        $this->getBrowser()->jsonRequest('PATCH', '/api/_action/seo-url/canonical', $seoUrl);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(204, $response->getStatusCode(), (string) $response->getContent());

        $seoUrls = $this->getSeoUrls($id, true, $salesChannelId);

        static::assertCount(0, $seoUrls);

        $productUpdate = [
            'id' => $id,
            'name' => 'unused name',
        ];
        $this->getBrowser()->jsonRequest('PATCH', '/api/product/' . $id, $productUpdate);

        $seoUrls = $this->getSeoUrls($id, true, $salesChannelId);

        static::assertCount(0, $seoUrls);
    }

    public function testPreviewWithPrepareCriteriaMethodActiveProductFiltering(): void
    {
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        // We need to create enough inactive products to test the limit=10 behavior
        $inactiveProductIds = [];
        for ($i = 1; $i <= 10; ++$i) {
            $inactiveProductId = $this->createTestProduct($salesChannelId, ['name' => "Inactive Product $i", 'active' => false]);
            $inactiveProductIds[] = $inactiveProductId;
        }

        // Create an active product that should be returned
        $activeProductId = $this->createTestProduct($salesChannelId);
        $this->getBrowser()->jsonRequest('PATCH', '/api/product/' . $activeProductId, [
            'id' => $activeProductId,
            'name' => 'Active Product',
            'active' => true,
        ]);

        $data = [
            'routeName' => 'frontend.detail.page',
            'entityName' => static::getContainer()->get(ProductDefinition::class)->getEntityName(),
            'template' => '{{ product.name }}',
            'salesChannelId' => $salesChannelId,
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/preview', $data);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $content = $response->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(1, $data, 'Should return exactly 1 active product (prepareCriteria filters out inactive products)');

        $foreignKeys = array_column($data, 'foreignKey');
        static::assertContains($activeProductId, $foreignKeys, 'Active product should be included');

        foreach ($inactiveProductIds as $inactiveProductId) {
            static::assertNotContains($inactiveProductId, $foreignKeys, "Inactive product $inactiveProductId should be filtered out by prepareCriteria");
        }
    }

    /**
     * @return array<Product>
     */
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
        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());

        $content = $this->getBrowser()->getResponse()->getContent();

        static::assertIsString($content);

        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['data'];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createTestProduct(string $salesChannelId = TestDefaults::SALES_CHANNEL, array $data = []): string
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
        $this->getBrowser()->jsonRequest('POST', '/api/product', array_merge($product, $data));

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
        $this->getBrowser()->jsonRequest('POST', '/api/category', $product);

        return $id;
    }
}
