<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Seo\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\Exception\SeoUrlRouteNotFoundException;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\Seo\StorefrontSalesChannelTestHelper;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;

class SeoActionControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    public function setUp(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->exec('DELETE FROM sales_channel');
        $connection->exec('DELETE FROM product');
    }

    public function testValidateEmpty(): void
    {
        $this->getBrowser()->request('POST', '/api/' . $this->getApiVersion() . '/_action/seo-url-template/validate');
        $response = $this->getBrowser()->getResponse();
        $result = json_decode($response->getContent(), true);

        static::assertNotEmpty($result['errors']);
        static::assertEquals(400, $response->getStatusCode());
    }

    public function testValidateInvalid(): void
    {
        $this->createTestProduct();
        $template = new SeoUrlTemplateEntity();
        $template->setRouteName('frontend.detail.page');
        $template->setTemplate('{{ product.name }');
        $template->setEntityName($this->getContainer()->get(ProductDefinition::class)->getEntityName());
        $template->setSalesChannelId(Defaults::SALES_CHANNEL);

        $this->getBrowser()->request('POST', '/api/' . $this->getApiVersion() . '/_action/seo-url-template/validate', $template->jsonSerialize());
        $response = $this->getBrowser()->getResponse();
        $result = json_decode($response->getContent(), true);

        static::assertNotEmpty($result['errors'] ?? []);
        static::assertEquals(400, $response->getStatusCode());
    }

    public function testValidateValid(): void
    {
        $this->createTestProduct();
        $template = new SeoUrlTemplateEntity();
        $template->setRouteName('frontend.detail.page');
        $template->setTemplate('{{ product.name }}');
        $template->setEntityName($this->getContainer()->get(ProductDefinition::class)->getEntityName());
        $template->setSalesChannelId(null);

        $this->getBrowser()->request('POST', '/api/' . $this->getApiVersion() . '/_action/seo-url-template/validate', $template->jsonSerialize());
        $response = $this->getBrowser()->getResponse();
        $result = json_decode($response->getContent(), true);

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
        $this->getBrowser()->request('POST', '/api/' . $this->getApiVersion() . '/product', $product);

        $data = [
            'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
            'entityName' => $this->getContainer()->get(ProductDefinition::class)->getEntityName(),
        ];
        $this->getBrowser()->request('POST', '/api/' . $this->getApiVersion() . '/_action/seo-url-template/context', $data);

        $response = $this->getBrowser()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        static::assertNotNull($data['product'] ?? null);
    }

    public function testPreview(): void
    {
        $this->createTestProduct();

        $data = [
            'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
            'entityName' => $this->getContainer()->get(ProductDefinition::class)->getEntityName(),
            'template' => '{{ product.name }}',
            'salesChannelId' => null,
        ];
        $this->getBrowser()->request('POST', '/api/' . $this->getApiVersion() . '/_action/seo-url-template/preview', $data);

        $response = $this->getBrowser()->getResponse();
        static::assertEquals(200, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true);

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
        $this->getBrowser()->request('POST', '/api/' . $this->getApiVersion() . '/_action/seo-url-template/preview', $data);

        $response = $this->getBrowser()->getResponse();
        static::assertEquals(200, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true);

        $urls = array_column($data, 'seoPathInfo');
        static::assertContains('B/', $urls);
    }

    public function testUnknownRoute(): void
    {
        $template = new SeoUrlTemplateEntity();
        $template->setRouteName('unknown.route');
        $template->setTemplate('{{ product.name }}');
        $template->setEntityName($this->getContainer()->get(ProductDefinition::class)->getEntityName());
        $template->setSalesChannelId(Defaults::SALES_CHANNEL);

        $this->getBrowser()->request('POST', '/api/' . $this->getApiVersion() . '/_action/seo-url-template/validate', $template->jsonSerialize());
        $response = $this->getBrowser()->getResponse();
        $result = json_decode($response->getContent(), true);

        static::assertArrayHasKey('errors', $result);
        static::assertEquals(404, $response->getStatusCode());

        static::assertEquals(SeoUrlRouteNotFoundException::ERROR_CODE, $result['errors'][0]['code']);
    }

    public function testUpdateDefaultCanonical(): void
    {
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $id = $this->createTestProduct();

        $seoUrls = $this->getSeoUrls($id, true, $salesChannelId);
        static::assertCount(1, $seoUrls);

        $seoUrl = $seoUrls[0]['attributes'];
        static::assertFalse($seoUrl['isModified']);

        $newSeoPathInfo = 'my-awesome-seo-path';
        $seoUrl['seoPathInfo'] = $newSeoPathInfo;
        $seoUrl['isModified'] = true;

        // modify canonical
        $this->getBrowser()->request('PATCH', '/api/' . $this->getApiVersion() . '/_action/seo-url/canonical', $seoUrl);
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
        $this->getBrowser()->request('PATCH', '/api/' . $this->getApiVersion() . '/product/' . $id, $productUpdate);

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

        $id = $this->createTestProduct();

        $seoUrls = $this->getSeoUrls($id, true, $salesChannelId);
        static::assertCount(1, $seoUrls);

        $seoUrl = $seoUrls[0]['attributes'];
        static::assertFalse($seoUrl['isModified']);

        $newSeoPathInfo = 'my-awesome-seo-path';
        $seoUrl['seoPathInfo'] = $newSeoPathInfo;
        $seoUrl['isModified'] = true;
        $seoUrl['salesChannelId'] = $salesChannelId;

        // modify canonical
        $this->getBrowser()->request('PATCH', '/api/' . $this->getApiVersion() . '/_action/seo-url/canonical', $seoUrl);
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
        $this->getBrowser()->request('PATCH', '/api/' . $this->getApiVersion() . '/product/' . $id, $productUpdate);

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
        $this->getBrowser()->request('GET', '/api/' . $this->getApiVersion() . '/product/' . $id . '/seoUrls', $params);
        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());

        $content = $this->getBrowser()->getResponse()->getContent();

        return json_decode($content, true)['data'];
    }

    private function createTestProduct(): string
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
        ];
        $this->getBrowser()->request('POST', '/api/' . $this->getApiVersion() . '/product', $product);

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
        $this->getBrowser()->request('POST', '/api/' . $this->getApiVersion() . '/category', $product);

        return $id;
    }

    private function getApiVersion()
    {
        $supportedApiVersions = $this->getContainer()->getParameter('kernel.supported_api_versions');
        $sortedSupportedApiVersions = array_values($supportedApiVersions);
        usort($sortedSupportedApiVersions, 'version_compare');

        return 'v' . array_pop($sortedSupportedApiVersions);
    }
}
