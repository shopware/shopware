<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Seo\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\Exception\SeoUrlRouteNotFoundException;
use Shopware\Core\Content\Seo\SeoException;
use Shopware\Core\Content\Seo\SeoUrlRoute\ProductStoreApiUrlRoute;
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
        $template->setRouteName(ProductPageSeoUrlRoute::ROUTE_NAME);
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
        $template->setRouteName(ProductPageSeoUrlRoute::ROUTE_NAME);
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
        $template->setRouteName(ProductPageSeoUrlRoute::ROUTE_NAME);
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
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');
        $this->createTestProduct($salesChannelId);

        $data = [
            'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
            'entityName' => static::getContainer()->get(ProductDefinition::class)->getEntityName(),
            'template' => '{{ product.undefinedProperty }}',
            'salesChannelId' => $salesChannelId,
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

    public function testPreviewForHeadlessStoreApiRoute(): void
    {
        $salesChannelId = Uuid::randomHex();
        $this->createSalesChannelContext([
            'id' => $salesChannelId,
            'typeId' => Defaults::SALES_CHANNEL_TYPE_API,
            'name' => 'test',
            'domains' => [
                [
                    'url' => 'https://foo.bar',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'isExternalStorefront' => true,
                ],
            ],
        ]);

        $this->createTestProduct($salesChannelId);

        $data = [
            'routeName' => ProductStoreApiUrlRoute::ROUTE_NAME,
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

        static::assertSame('https://foo.bar/test', $data[0]['seoPathInfo']);
    }

    public function testPreviewForHeadlessStoreApiRouteWithEmptyTemplateIsNotInvalid(): void
    {
        $salesChannelId = Uuid::randomHex();
        $this->createSalesChannelContext(['id' => $salesChannelId, 'typeId' => Defaults::SALES_CHANNEL_TYPE_API, 'name' => 'test']);

        $data = [
            'routeName' => ProductStoreApiUrlRoute::ROUTE_NAME,
            'entityName' => static::getContainer()->get(ProductDefinition::class)->getEntityName(),
            'template' => '',
            'salesChannelId' => $salesChannelId,
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/preview', $data);

        static::assertSame(204, $this->getBrowser()->getResponse()->getStatusCode());
    }

    public function testPreviewForHeadlessStoreApiRouteWithFullUrlButNoEntities(): void
    {
        $salesChannelId = Uuid::randomHex();
        $this->createSalesChannelContext(['id' => $salesChannelId, 'typeId' => Defaults::SALES_CHANNEL_TYPE_API, 'name' => 'test']);

        $data = [
            'routeName' => ProductStoreApiUrlRoute::ROUTE_NAME,
            'entityName' => static::getContainer()->get(ProductDefinition::class)->getEntityName(),
            'template' => 'https://foo.bar/{{ product.name }}',
            'salesChannelId' => $salesChannelId,
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/preview', $data);

        static::assertSame(204, $this->getBrowser()->getResponse()->getStatusCode());
    }

    public function testGetSeoContextForHeadlessStoreApiRoute(): void
    {
        $salesChannelId = Uuid::randomHex();
        $this->createSalesChannelContext(['id' => $salesChannelId, 'typeId' => Defaults::SALES_CHANNEL_TYPE_API, 'name' => 'test']);

        $this->createTestProduct($salesChannelId);

        // The store-api route name resolves to the product definition via the tagged entity routes; no entityName needed.
        $data = ['routeName' => ProductStoreApiUrlRoute::ROUTE_NAME];
        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/context', $data);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $content = $response->getContent();
        static::assertIsString($content);
        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotNull($data['product'] ?? null);
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

    /**
     * Regression for shopware/shopware#4413: a write-protected (isModified=true) canonical
     * SEO URL must be editable again and resettable to the template-generated path.
     */
    public function testUpdateWriteProtectedCanonicalCanBeEditedAndReset(): void
    {
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $id = $this->createTestProduct($salesChannelId);

        $seoUrls = $this->getSeoUrls($id, true, $salesChannelId);
        static::assertCount(1, $seoUrls);
        $initialPathInfo = $seoUrls[0]['attributes']['seoPathInfo'];

        // Initial manual modification – writes a write-protected URL.
        $manualPath = 'manual-path';
        $firstPayload = $seoUrls[0]['attributes'];
        $firstPayload['seoPathInfo'] = $manualPath;
        $firstPayload['isModified'] = true;

        $this->getBrowser()->jsonRequest('PATCH', '/api/_action/seo-url/canonical', $firstPayload);
        static::assertSame(204, $this->getBrowser()->getResponse()->getStatusCode());

        $seoUrls = $this->getSeoUrls($id, true, $salesChannelId);
        static::assertCount(1, $seoUrls);
        static::assertTrue($seoUrls[0]['attributes']['isModified']);
        static::assertSame($manualPath, $seoUrls[0]['attributes']['seoPathInfo']);

        // (a) Manual edit of the already write-protected SEO URL must persist.
        $editedPath = 'manual-path-edited';
        $editPayload = $seoUrls[0]['attributes'];
        $editPayload['seoPathInfo'] = $editedPath;
        $editPayload['isModified'] = true;

        $this->getBrowser()->jsonRequest('PATCH', '/api/_action/seo-url/canonical', $editPayload);
        static::assertSame(204, $this->getBrowser()->getResponse()->getStatusCode());

        $seoUrls = $this->getSeoUrls($id, true, $salesChannelId);
        static::assertCount(1, $seoUrls);
        static::assertTrue($seoUrls[0]['attributes']['isModified']);
        static::assertSame($editedPath, $seoUrls[0]['attributes']['seoPathInfo']);

        // (b) Clearing the write-protection flag must also actually clear it on the
        // persisted canonical (previously the overwrite check silently kept isModified=true).
        $clearPayload = $seoUrls[0]['attributes'];
        $clearPayload['seoPathInfo'] = 'manual-path-final';
        $clearPayload['isModified'] = false;

        $this->getBrowser()->jsonRequest('PATCH', '/api/_action/seo-url/canonical', $clearPayload);
        static::assertSame(204, $this->getBrowser()->getResponse()->getStatusCode());

        $seoUrls = $this->getSeoUrls($id, true, $salesChannelId);
        $canonical = null;
        foreach ($seoUrls as $url) {
            if ($url['attributes']['isCanonical'] ?? true) {
                $canonical = $url['attributes'];
                break;
            }
        }

        static::assertNotNull($canonical, 'A canonical SEO URL must still exist after clearing the write-protection flag');
        static::assertFalse($canonical['isModified'], 'Canonical SEO URL must be cleared of the write-protection flag');
        static::assertSame('manual-path-final', $canonical['seoPathInfo']);

        // The original template-generated path is still reachable (kept as a
        // redirecting non-canonical entry so old links keep working).
        static::assertNotEmpty(
            array_filter(
                $this->getSeoUrls($id, null, $salesChannelId),
                static fn (array $url): bool => $url['attributes']['seoPathInfo'] === $initialPathInfo
            ),
            'Original template path must be retained as a non-canonical redirect'
        );
    }

    /**
     * Regression for shopware/shopware#4413 (same-path reset): a write-protected canonical whose
     * path already equals the template output must still be resettable through the admin endpoint.
     * Clearing the flag without changing the path must actually drop isModified instead of silently
     * keeping it write-protected.
     *
     * The write-protected canonical is seeded directly so the test does not depend on storefront
     * product SEO indexing, but the reset itself goes through the real `/api/_action/seo-url/canonical`
     * endpoint (validation + SeoUrlPersister + DB).
     */
    public function testResetWriteProtectedCanonicalWithUnchangedPath(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');
        $productId = $this->createTestProduct($salesChannelId);

        $route = ProductPageSeoUrlRoute::ROUTE_NAME;
        $path = 'red-shoe';

        // Normalise: drop any auto-generated SEO URLs for the product so the seeded
        // write-protected canonical is the only row, regardless of whether the environment
        // generated one on product creation.
        $connection->executeStatement(
            'DELETE FROM seo_url WHERE foreign_key = :fk',
            ['fk' => Uuid::fromHexToBytes($productId)]
        );

        // Seed a write-protected canonical whose path already equals the template output
        // (mimics a migrated/manually created SEO URL).
        $connection->insert('seo_url', [
            'id' => Uuid::randomBytes(),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
            'foreign_key' => Uuid::fromHexToBytes($productId),
            'route_name' => $route,
            'path_info' => '/detail/' . $productId,
            'seo_path_info' => $path,
            'is_canonical' => 1,
            'is_modified' => 1,
            'is_deleted' => 0,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        // Reset through the admin endpoint: same path, isModified=false.
        $this->getBrowser()->jsonRequest('PATCH', '/api/_action/seo-url/canonical', [
            'foreignKey' => $productId,
            'routeName' => $route,
            'pathInfo' => '/detail/' . $productId,
            'seoPathInfo' => $path,
            'salesChannelId' => $salesChannelId,
            'isModified' => false,
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(204, $response->getStatusCode(), (string) $response->getContent());

        $canonicals = $connection->fetchAllAssociative(
            'SELECT seo_path_info, is_modified FROM seo_url WHERE foreign_key = :fk AND is_canonical = 1',
            ['fk' => Uuid::fromHexToBytes($productId)]
        );

        static::assertCount(1, $canonicals, 'Exactly one canonical SEO URL must remain');
        static::assertSame($path, $canonicals[0]['seo_path_info']);
        static::assertSame(
            0,
            (int) $canonicals[0]['is_modified'],
            'Write-protection flag must be cleared even when the path did not change'
        );
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
        $this->createSalesChannelContext([
            'id' => $salesChannelId,
            'typeId' => Defaults::SALES_CHANNEL_TYPE_API,
            'name' => 'test',
            'domains' => [
                [
                    'url' => 'https://foo.bar',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'isExternalStorefront' => true,
                ],
            ],
        ]);

        $id = $this->createTestProduct($salesChannelId);

        $seoUrls = $this->getSeoUrls($id, true, $salesChannelId);

        static::assertCount(1, $seoUrls);

        $seoUrl = [
            'foreignKey' => $id,
            'seoPathInfo' => 'my-awesome-seo-path',
            'pathInfo' => '/store-api/product/' . $id,
            'salesChannelId' => $salesChannelId,
            'isModified' => true,
            'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
        ];

        // modify canonical
        $this->getBrowser()->jsonRequest('PATCH', '/api/_action/seo-url/canonical', $seoUrl);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(204, $response->getStatusCode(), (string) $response->getContent());

        $seoUrls = $this->getSeoUrls($id, true, $salesChannelId);

        static::assertCount(1, $seoUrls);

        $productUpdate = [
            'id' => $id,
            'name' => 'unused name',
        ];
        $this->getBrowser()->jsonRequest('PATCH', '/api/product/' . $id, $productUpdate);

        $seoUrls = $this->getSeoUrls($id, true, $salesChannelId);

        static::assertCount(1, $seoUrls);
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
            'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
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
