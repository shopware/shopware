<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Seo\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use function Flag\skipTestNext741;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Framework\Seo\DataAbstractionLayer\Indexing\SeoUrlIndexer;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;

class SeoUrlIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var SeoUrlIndexer
     */
    private $indexer;

    /**
     * @var EntityRepositoryInterface
     */
    private $templateRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    public function setUp(): void
    {
        parent::setUp();

        skipTestNext741($this);

        $this->indexer = $this->getContainer()->get(SeoUrlIndexer::class);
        $this->templateRepository = $this->getContainer()->get('seo_url_template.repository');
        $this->productRepository = $this->getContainer()->get('product.repository');

        $connection = $this->getContainer()->get(Connection::class);
        $connection->exec('DELETE FROM `sales_channel`');
    }

    public function testDefaultNew(): void
    {
        $salesChannel = $this->createSalesChannel(Uuid::randomHex(), 'test');
        $id = Uuid::randomHex();
        $this->upsertProduct(['id' => $id, 'name' => 'awesome product', 'productNumber' => 'P1']);

        $context = $this->createContext($salesChannel);
        $criteria = new Criteria([$id]);
        $product = $this->productRepository->search($criteria, $context)->first();

        static::assertNotNull($product->getExtension('canonicalUrl'));
        $canonicalUrl = $product->getExtension('canonicalUrl');
        static::assertEquals('awesome-product/P1', $canonicalUrl->getSeoPathInfo());

        $seoUrls = $this->getSeoUrls($salesChannel->getId(), $id);
        $canonicalUrls = $seoUrls->filterByProperty('isCanonical', true);
        $nonCanonicals = $seoUrls->filterByProperty('isCanonical', false);

        static::assertEquals($canonicalUrl->getId(), $canonicalUrls->first()->getId());

        static::assertCount(1, $canonicalUrls);
        static::assertCount(0, $nonCanonicals);
        static::assertCount(1, $seoUrls);
    }

    public function testDefaultUpdateSamePath(): void
    {
        $salesChannel = $this->createSalesChannel(Uuid::randomHex(), 'test');
        $id = Uuid::randomHex();

        $this->upsertProduct(['id' => $id, 'name' => 'awesome product', 'productNumber' => 'P1']);
        $this->upsertProduct(['id' => $id, 'name' => 'awesome product', 'description' => 'this description should not matter', 'productNumber' => 'P1']);

        $context = $this->createContext($salesChannel);
        $product = $this->productRepository->search(new Criteria([$id]), $context)->first();
        static::assertNotNull($product->getExtension('canonicalUrl'));

        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = $product->getExtension('canonicalUrl');
        static::assertEquals('awesome-product/P1', $seoUrl->getSeoPathInfo());

        $seoUrls = $this->getSeoUrls($salesChannel->getId(), $id);
        $canonicalUrls = $seoUrls->filterByProperty('isCanonical', true);
        $nonCanonicals = $seoUrls->filterByProperty('isCanonical', false);

        static::assertEquals($seoUrl->getId(), $canonicalUrls->first()->getId());

        static::assertCount(1, $canonicalUrls);
        static::assertCount(0, $nonCanonicals);
        static::assertCount(1, $seoUrls);
    }

    public function testDefaultUpdateDifferentPath(): void
    {
        $salesChannel = $this->createSalesChannel(Uuid::randomHex(), 'test');
        $id = Uuid::randomHex();

        $this->upsertProduct(['id' => $id, 'name' => 'awesome product', 'productNumber' => 'P1']);
        $this->upsertProduct(['id' => $id, 'name' => 'awesome product v2', 'productNumber' => 'P1']);
        $this->upsertProduct(['id' => $id, 'name' => 'awesome product v3', 'productNumber' => 'P1']);

        $context = $this->createContext($salesChannel);
        $product = $this->productRepository->search(new Criteria([$id]), $context)->first();
        static::assertNotNull($product->getExtension('canonicalUrl'));

        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = $product->getExtension('canonicalUrl');
        static::assertEquals('awesome-product-v3/P1', $seoUrl->getSeoPathInfo());

        $seoUrls = $this->getSeoUrls($salesChannel->getId(), $id);
        $canonicalUrls = $seoUrls->filterByProperty('isCanonical', true);
        $nonCanonicals = $seoUrls->filterByProperty('isCanonical', false);

        static::assertEquals($seoUrl->getId(), $canonicalUrls->first()->getId());

        static::assertCount(1, $canonicalUrls);
        static::assertCount(2, $nonCanonicals);
        static::assertCount(3, $seoUrls);
    }

    public function testCustomNew(): void
    {
        $salesChannel = $this->createSalesChannel(Uuid::randomHex(), 'test');
        $id = Uuid::randomHex();
        $this->upsertTemplate([
            'id' => $id,
            'salesChannelId' => $salesChannel->getId(),
            'template' => 'foo/{{ product.name }}/bar',
        ]);

        $this->upsertProduct(['id' => $id, 'name' => 'awesome product']);
        $context = $this->createContext($salesChannel);

        /** @var ProductEntity $first */
        $first = $this->productRepository->search(new Criteria([$id]), $context)->first();
        static::assertNotNull($first->getExtension('canonicalUrl'));

        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = $first->getExtension('canonicalUrl');
        static::assertEquals($first->getId(), $seoUrl->getForeignKey());
        static::assertEquals(ProductPageSeoUrlRoute::ROUTE_NAME, $seoUrl->getRouteName());
        static::assertEquals('/detail/' . $id, $seoUrl->getPathInfo());
        static::assertEquals('foo/awesome-product/bar', $seoUrl->getSeoPathInfo());
        static::assertTrue($seoUrl->getIsCanonical());
    }

    public function testCustomUpdateSamePath(): void
    {
        $id = Uuid::randomHex();
        $salesChannel = $this->createSalesChannel(Uuid::randomHex(), 'test');
        $this->upsertTemplate([
            'id' => $id,
            'salesChannelId' => $salesChannel->getId(),
            'template' => 'foo/{{ product.name}}/bar',
        ]);
        $this->upsertProduct(['id' => $id, 'name' => 'awesome product']);
        $this->upsertProduct(['id' => $id, 'name' => 'awesome product', 'description' => 'should not matter']);

        /** @var ProductEntity $first */
        $first = $this->productRepository->search(new Criteria([$id]), $this->createContext($salesChannel))->first();
        static::assertNotNull($first->getExtension('canonicalUrl'));

        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = $first->getExtension('canonicalUrl');
        static::assertEquals('foo/awesome-product/bar', $seoUrl->getSeoPathInfo());
        static::assertTrue($seoUrl->getIsCanonical());

        $seoUrls = $this->getSeoUrls($salesChannel->getId(), $id);
        $canonicalUrls = $seoUrls->filterByProperty('isCanonical', true);
        $nonCanonicals = $seoUrls->filterByProperty('isCanonical', false);

        static::assertEquals($seoUrl->getId(), $canonicalUrls->first()->getId());

        static::assertCount(1, $canonicalUrls);
        static::assertCount(0, $nonCanonicals);
        static::assertCount(1, $seoUrls);
    }

    public function testCustomUpdateDifferentPath(): void
    {
        $id = Uuid::randomHex();
        $salesChannel = $this->createSalesChannel(Uuid::randomHex(), 'test');
        $this->upsertTemplate([
            'id' => $id,
            'salesChannelId' => $salesChannel->getId(),
            'template' => 'foo/{{ product.name }}/bar',
        ]);
        $this->upsertProduct(['id' => $id, 'name' => 'awesome product']);
        $this->upsertProduct(['id' => $id, 'name' => 'awesome product improved']);
        $this->upsertProduct(['id' => $id, 'name' => 'awesome product improved again']);

        /** @var ProductEntity $first */
        $first = $this->productRepository->search(new Criteria([$id]), $this->createContext($salesChannel))->first();
        static::assertNotNull($first->getExtension('canonicalUrl'));

        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = $first->getExtension('canonicalUrl');
        static::assertEquals('foo/awesome-product-improved-again/bar', $seoUrl->getSeoPathInfo());

        $seoUrls = $this->getSeoUrls($salesChannel->getId(), $id);
        $canonicalUrls = $seoUrls->filterByProperty('isCanonical', true);
        $nonCanonicals = $seoUrls->filterByProperty('isCanonical', false);

        static::assertEquals($seoUrl->getId(), $canonicalUrls->first()->getId());

        static::assertCount(1, $canonicalUrls);
        static::assertCount(2, $nonCanonicals);
        static::assertCount(3, $seoUrls);
    }

    /**
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    public function testUpdateWithUpdatedTemplate(): void
    {
        $id = Uuid::randomHex();
        $salesChannel = $this->createSalesChannel(Uuid::randomHex(), 'test');
        $this->upsertTemplate([
            'id' => $id,
            'salesChannelId' => $salesChannel->getId(),
            'template' => 'foo/{{ product.name }}/bar',
        ]);

        $this->upsertProduct(['id' => $id, 'name' => 'awesome product']);
        $this->upsertTemplate([
            'id' => $id,
            'salesChannelId' => $salesChannel->getId(),
            'template' => 'bar/{{ product.name }}/baz',
        ]);
        $this->upsertProduct(['id' => $id, 'name' => 'awesome product improved']);
        $this->upsertProduct(['id' => $id, 'name' => 'awesome product improved']);

        $context = new Context(new SalesChannelApiSource($salesChannel->getId()));
        /** @var ProductEntity $first */
        $first = $this->productRepository->search(new Criteria([$id]), $context)->first();

        static::assertNotNull($first);
        static::assertNotNull($first->getExtension('canonicalUrl'));

        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = $first->getExtension('canonicalUrl');
        static::assertEquals($first->getId(), $seoUrl->getForeignKey());
        static::assertEquals(ProductPageSeoUrlRoute::ROUTE_NAME, $seoUrl->getRouteName());
        static::assertEquals('/detail/' . $id, $seoUrl->getPathInfo());
        static::assertEquals('bar/awesome-product-improved/baz', $seoUrl->getSeoPathInfo());
        static::assertTrue($seoUrl->getIsCanonical());

        $seoUrls = $this->getSeoUrls($salesChannel->getId(), $id);
        $canonicalUrls = $seoUrls->filterByProperty('isCanonical', true);
        $nonCanonicals = $seoUrls->filterByProperty('isCanonical', false);

        static::assertEquals($seoUrl->getId(), $canonicalUrls->first()->getId());

        static::assertCount(1, $canonicalUrls);
        static::assertCount(1, $nonCanonicals);
        static::assertCount(2, $seoUrls);

        static::assertEquals('foo/awesome-product/bar', $nonCanonicals->first()->getSeoPathInfo());
    }

    public function testIsMarkedAsDeleted(): void
    {
        $salesChannel = $this->createSalesChannel(Uuid::randomHex(), 'test');
        $id = Uuid::randomHex();
        $this->upsertProduct(['id' => $id, 'name' => 'awesome product', 'productNumber' => 'P1']);
        $this->upsertProduct(['id' => $id, 'name' => 'awesome product v2', 'productNumber' => 'P1']);

        $context = $this->createContext($salesChannel);
        $product = $this->productRepository->search(new Criteria([$id]), $context)->first();

        static::assertNotNull($product->getExtension('canonicalUrl'));
        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = $product->getExtension('canonicalUrl');
        static::assertEquals('awesome-product-v2/P1', $seoUrl->getSeoPathInfo());
        static::assertFalse($seoUrl->getIsDeleted());

        $this->productRepository->delete([['id' => $id]], $context);

        $seoUrls = $this->getSeoUrls($salesChannel->getId(), $id);
        static::assertCount(2, $seoUrls);
        static::assertCount(2, $seoUrls->filterByProperty('isDeleted', true));
        static::assertCount(0, $seoUrls->filterByProperty('isDeleted', false));
    }

    public function testAutoSlugify(): void
    {
        $salesChannel = $this->createSalesChannel(Uuid::randomHex(), 'test');
        $this->upsertTemplate([
            'id' => Uuid::randomHex(),
            'salesChannelId' => $salesChannel->getId(),
            'template' => '{{ product.name }}', // no slugify!
        ]);

        $id = Uuid::randomHex();
        $this->upsertProduct(['id' => $id, 'name' => 'foo bar']);

        $context = $this->createContext($salesChannel);
        $product = $this->productRepository->search(new Criteria([$id]), $context)->first();

        static::assertNotNull($product->getExtension('canonicalUrl'));
        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = $product->getExtension('canonicalUrl');
        static::assertEquals('foo-bar', $seoUrl->getSeoPathInfo());
        static::assertFalse($seoUrl->getIsDeleted());
    }

    public function testEntityIsSkippedOnRuntimeError(): void
    {
        $salesChannel = $this->createSalesChannel(Uuid::randomHex(), 'test');
        $this->upsertTemplate([
            'id' => Uuid::randomHex(),
            'salesChannelId' => $salesChannel->getId(),
            'template' => '{{ product.customFields.foo }}', // this throws a runtime error, because attributes is null
        ]);

        $id = Uuid::randomHex();
        $this->upsertProduct([
            'id' => $id,
            'name' => 'foo',
        ]);

        $context = $this->createContext($salesChannel);

        $criteria = (new Criteria([$id]))->addAssociation('seoUrls');
        $product = $this->productRepository->search($criteria, $context)->first();
        $seoUrls = $product->getExtension('seoUrls')->filterBySalesChannelId($salesChannel->getId());
        static::assertEmpty($seoUrls);

        $this->upsertProduct([
            'id' => $id,
            'name' => 'foo',
            'customFields' => [
                'foo' => 'bar',
            ],
        ]);

        $criteria = (new Criteria([$id]))->addAssociation('seoUrls');
        $product = $this->productRepository->search($criteria, $context)->first();
        $seoUrls = $product->getExtension('seoUrls')->filterBySalesChannelId($salesChannel->getId());

        static::assertNotEmpty($seoUrls);
    }

    public function testDuplicatesAreMarkedAsInvalid(): void
    {
        $salesChannel = $this->createSalesChannel(Uuid::randomHex(), 'custom template', Defaults::LANGUAGE_SYSTEM, []);

        $this->upsertTemplate([
            'id' => Uuid::randomHex(),
            'salesChannelId' => $salesChannel->getId(),
            'template' => '{{ product.name }}',
        ]);

        $id = Uuid::randomHex();
        $this->upsertProduct([
            'id' => $id,
            'productNumber' => '1',
            'name' => 'foo',
        ]);

        $dupId = Uuid::randomHex();
        $this->upsertProduct([
            'id' => $dupId,
            'productNumber' => '2',
            'name' => 'foo',
        ]);
        $this->upsertProduct([
            'id' => $dupId,
            'productNumber' => '2',
            'name' => 'foo',
        ]);

        $context = $this->createContext($salesChannel);
        $criteria = new Criteria([$id, $dupId]);
        $criteria->addAssociation('seoUrls');
        $products = $this->productRepository->search($criteria, $context);

        /** @var ProductEntity $validProduct */
        $validProduct = $products->filterByProperty('id', $id)->first();

        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $validProduct->getExtension('seoUrls')->filterByProperty('salesChannelId', $salesChannel->getId());

        $validSeoUrls = $seoUrls->filterByProperty('isValid', true);

        static::assertCount(1, $validSeoUrls);
        static::assertEquals($id, $validSeoUrls->first()->getForeignKey());

        $invalidSeoUrls = $seoUrls->filterByProperty('isValid', false);
        static::assertCount(0, $invalidSeoUrls);

        /** @var ProductEntity $invalidProduct */
        $invalidProduct = $products->filterByProperty('id', $dupId)->first();

        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $invalidProduct->getExtension('seoUrls')->filterByProperty('salesChannelId', $salesChannel->getId());

        $validSeoUrls = $seoUrls->filterByProperty('isValid', true);
        static::assertCount(0, $validSeoUrls);

        $invalidSeoUrls = $seoUrls->filterByProperty('isValid', false);
        static::assertCount(1, $invalidSeoUrls);
        static::assertEquals($dupId, $invalidSeoUrls->first()->getForeignKey());
    }

    public function testMultiCreate(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $products = [
            [
                'id' => $id1,
                'manufacturer' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'amazing brand',
                ],
                'name' => 'foo',
                'productNumber' => 'P1',
                'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'tax'],
                'price' => ['gross' => 10, 'net' => 12, 'linked' => false],
                'stock' => 0,
            ],
            [
                'id' => $id2,
                'manufacturer' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'amazing brand 2',
                ],
                'name' => 'bar',
                'productNumber' => 'P2',
                'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'tax'],
                'price' => ['gross' => 10, 'net' => 12, 'linked' => false],
                'stock' => 0,
            ],
        ];
        $this->productRepository->upsert($products, Context::createDefaultContext());

        $criteria = new Criteria([$id1, $id2]);
        $criteria->addAssociation('seoUrls');
        $products = $this->productRepository->search($criteria, Context::createDefaultContext());

        /** @var ProductEntity $first */
        $first = $products->first();
        $seoUrls = $first->getExtension('seoUrls');
        static::assertCount(1, $seoUrls);

        $last = $products->last();
        $seoUrls = $last->getExtension('seoUrls');
        static::assertCount(1, $seoUrls);
    }

    public function testIndex(): void
    {
        $productDefinition = $this->getContainer()->get(ProductDefinition::class);
        /** @var EntityWriter $writer */
        $writer = $this->getContainer()->get(EntityWriter::class);

        $id = Uuid::randomHex();
        $products = [
            [
                'id' => $id,
                'manufacturer' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'amazing brand',
                ],
                'name' => 'foo',
                'productNumber' => 'P1',
                'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'tax'],
                'price' => ['gross' => 10, 'net' => 12, 'linked' => false],
                'stock' => 0,
            ],
        ];
        // the writer does not fire events, so seo urls are not created automatically
        $writer->insert($productDefinition, $products, WriteContext::createFromContext(Context::createDefaultContext()));

        /** @var EntityRepositoryInterface $productRepo */
        $productRepo = $this->getContainer()->get('product.repository');

        $indexer = $this->getContainer()->get(SeoUrlIndexer::class);
        $indexer->index(new \DateTimeImmutable());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('seoUrls');
        /** @var ProductEntity $product */
        $product = $productRepo->search($criteria, Context::createDefaultContext())->first();
        static::assertInstanceOf(SeoUrlCollection::class, $product->getExtension('seoUrls'));
        static::assertCount(1, $product->getExtension('seoUrls'));
    }

    private function createContext(SalesChannelEntity $salesChannel): Context
    {
        return new Context(new SalesChannelApiSource($salesChannel->getId()));
    }

    private function getSeoUrls(string $salesChannelId, string $productId): SeoUrlCollection
    {
        /** @var EntityRepositoryInterface $repo */
        $repo = $this->getContainer()->get('seo_url.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $criteria->addFilter(new EqualsFilter('foreignKey', $productId));

        return $repo->search($criteria, Context::createDefaultContext())->getEntities();
    }

    private function upsertTemplate($data): void
    {
        $seoUrlTemplateDefaults = [
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'entityName' => $this->getContainer()->get(ProductDefinition::class)->getEntityName(),
            'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
        ];
        $seoUrlTemplate = array_merge($seoUrlTemplateDefaults, $data);
        $this->templateRepository->upsert([$seoUrlTemplate], Context::createDefaultContext());
    }

    private function upsertProduct($data): void
    {
        $defaults = [
            'manufacturer' => [
                'id' => Uuid::randomHex(),
                'name' => 'amazing brand',
            ],
            'productNumber' => Uuid::randomHex(),
            'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'tax'],
            'price' => ['gross' => 10, 'net' => 12, 'linked' => false],
            'stock' => 0,
        ];
        $data = array_merge($defaults, $data);
        $this->productRepository->upsert([$data], Context::createDefaultContext());
    }

    private function createSalesChannel(string $id, string $name, string $defaultLanguageId = Defaults::LANGUAGE_SYSTEM, array $languageIds = []): SalesChannelEntity
    {
        /** @var EntityRepositoryInterface $repo */
        $repo = $this->getContainer()->get('sales_channel.repository');
        $languageIds[] = $defaultLanguageId;
        $languageIds = array_unique($languageIds);

        $domains = [];
        $languages = [];

        $paymentMethod = $this->getValidPaymentMethodId();
        $shippingMethod = $this->getValidShippingMethodId();
        $country = $this->getValidCountryId();

        foreach ($languageIds as $langId) {
            $languages[] = ['id' => $langId];
            $domains[] = [
                'languageId' => $langId,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://example.com/' . $langId,
            ];
        }

        $repo->upsert([[
            'id' => $id,
            'name' => $name,
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'accessKey' => 'foobar',
            'secretAccessKey' => 'foobar',
            'languageId' => $defaultLanguageId,
            'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => $paymentMethod,
            'shippingMethodId' => $shippingMethod,
            'countryId' => $country,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => $languages,
            'paymentMethods' => [['id' => $paymentMethod]],
            'shippingMethods' => [['id' => $shippingMethod]],
            'countries' => [['id' => $country]],
            'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'domains' => $domains,
            'navigationCategoryId' => $this->getValidCategoryId(),
        ]], Context::createDefaultContext());

        return $repo->search(new Criteria([$id]), Context::createDefaultContext())->first();
    }
}
