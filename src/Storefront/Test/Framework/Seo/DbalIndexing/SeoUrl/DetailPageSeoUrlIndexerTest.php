<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Seo\DbalIndexing\SeoUrl;

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
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Framework\Seo\DbalIndexing\SeoUrl\ProductDetailPageSeoUrlIndexer;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlEntity;

class DetailPageSeoUrlIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var ProductDetailPageSeoUrlIndexer
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

        $this->indexer = $this->getContainer()->get(ProductDetailPageSeoUrlIndexer::class);
        $this->templateRepository = $this->getContainer()->get('seo_url_template.repository');
        $this->productRepository = $this->getContainer()->get('product.repository');

        $connection = $this->getContainer()->get(Connection::class);
        $connection->exec('DELETE FROM `sales_channel`');
    }

    public function testDefaultNew(): void
    {
        $salesChannel = $this->createSalesChannel(Uuid::randomHex(), 'test');
        $id = Uuid::randomHex();
        $this->upsertProduct(['id' => $id, 'name' => 'awesome product']);

        $context = $this->createContext($salesChannel);
        $product = $this->productRepository->search(new Criteria([$id]), $context)->first();

        static::assertNotNull($product->getExtension('canonicalUrl'));
        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = $product->getExtension('canonicalUrl');
        static::assertEquals('awesome-product/' . $id, $seoUrl->getSeoPathInfo());

        $seoUrls = $this->getSeoUrls($salesChannel->getId(), $id);
        $canonicalUrls = $seoUrls->filterByProperty('isCanonical', true);
        $nonCanonicals = $seoUrls->filterByProperty('isCanonical', false);

        static::assertEquals($seoUrl->getId(), $canonicalUrls->first()->getId());

        static::assertCount(1, $canonicalUrls);
        static::assertCount(0, $nonCanonicals);
        static::assertCount(1, $seoUrls);
    }

    public function testDefaultUpdateSamePath(): void
    {
        $salesChannel = $this->createSalesChannel(Uuid::randomHex(), 'test');
        $id = Uuid::randomHex();

        $this->upsertProduct(['id' => $id, 'name' => 'awesome product']);
        $this->upsertProduct(['id' => $id, 'name' => 'awesome product', 'description' => 'this description should not matter']);

        $context = $this->createContext($salesChannel);
        $product = $this->productRepository->search(new Criteria([$id]), $context)->first();
        static::assertNotNull($product->getExtension('canonicalUrl'));

        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = $product->getExtension('canonicalUrl');
        static::assertEquals('awesome-product/' . $id, $seoUrl->getSeoPathInfo());

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

        $this->upsertProduct(['id' => $id, 'name' => 'awesome product']);
        $this->upsertProduct(['id' => $id, 'name' => 'awesome product v2']);
        $this->upsertProduct(['id' => $id, 'name' => 'awesome product v3']);

        $context = $this->createContext($salesChannel);
        $product = $this->productRepository->search(new Criteria([$id]), $context)->first();
        static::assertNotNull($product->getExtension('canonicalUrl'));

        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = $product->getExtension('canonicalUrl');
        static::assertEquals('awesome-product-v3/' . $id, $seoUrl->getSeoPathInfo());

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
        static::assertEquals(ProductDetailPageSeoUrlIndexer::ROUTE_NAME, $seoUrl->getRouteName());
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
        static::assertEquals(ProductDetailPageSeoUrlIndexer::ROUTE_NAME, $seoUrl->getRouteName());
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
        $this->upsertProduct(['id' => $id, 'name' => 'awesome product']);
        $this->upsertProduct(['id' => $id, 'name' => 'awesome product v2']);

        $context = $this->createContext($salesChannel);
        $product = $this->productRepository->search(new Criteria([$id]), $context)->first();

        static::assertNotNull($product->getExtension('canonicalUrl'));
        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = $product->getExtension('canonicalUrl');
        static::assertEquals('awesome-product-v2/' . $id, $seoUrl->getSeoPathInfo());
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

    public function testShortProperties(): void
    {
        $salesChannel = $this->createSalesChannel(Uuid::randomHex(), 'test');
        $this->upsertTemplate([
            'id' => Uuid::randomHex(),
            'salesChannelId' => $salesChannel->getId(),
            'template' => '{{ manufacturerName }}/{{ productName }}/{{ manufacturerNumber }}',
        ]);

        $id = Uuid::randomHex();
        $this->upsertProduct([
            'id' => $id,
            'name' => 'awesome product',
            'manufacturerNumber' => 'foo',
            'manufacturer' => [
                'id' => Uuid::randomHex(),
                'name' => 'amazing ag',
            ],
        ]);

        $context = $this->createContext($salesChannel);
        $childProduct = $this->productRepository->search(new Criteria([$id]), $context)->first();

        static::assertNotNull($childProduct->getExtension('canonicalUrl'));
        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = $childProduct->getExtension('canonicalUrl');
        static::assertEquals('amazing-ag/awesome-product/foo', $seoUrl->getSeoPathInfo());
        static::assertFalse($seoUrl->getIsDeleted());
    }

    public function testEntityIsSkippedOnRuntimeError(): void
    {
        $salesChannel = $this->createSalesChannel(Uuid::randomHex(), 'test');
        $this->upsertTemplate([
            'id' => Uuid::randomHex(),
            'salesChannelId' => $salesChannel->getId(),
            'template' => '{{ product.attributes.foo }}', // this throws a runtime error, because attributes is null
        ]);

        $id = Uuid::randomHex();
        $this->upsertProduct([
            'id' => $id,
            'name' => 'foo',
        ]);

        $context = $this->createContext($salesChannel);

        $criteria = (new Criteria([$id]))->addAssociation('seoUrls');
        $product = $this->productRepository->search($criteria, $context)->first();
        static::assertEmpty($product->getExtension('seoUrls'));

        $this->upsertProduct([
            'id' => $id,
            'name' => 'foo',
            'attributes' => [
                'foo' => 'bar',
            ],
        ]);

        $criteria = (new Criteria([$id]))->addAssociation('seoUrls');
        $product = $this->productRepository->search($criteria, $context)->first();
        $seoUrls = $product->getExtension('seoUrls');
        static::assertNotEmpty($seoUrls);
    }

    public function testDuplicatesAreMarkedAsInvalid(): void
    {
        $salesChannel = $this->createSalesChannel(Uuid::randomHex(), 'test');
        $this->upsertTemplate([
            'id' => Uuid::randomHex(),
            'salesChannelId' => $salesChannel->getId(),
            'template' => '{{ productName }}',
        ]);

        $id = Uuid::randomHex();
        $this->upsertProduct([
            'id' => $id,
            'name' => 'foo',
        ]);

        $dupId = Uuid::randomHex();
        $this->upsertProduct([
            'id' => $dupId,
            'name' => 'foo',
        ]);

        $context = $this->createContext($salesChannel);
        $criteria = new Criteria([$id, $dupId]);
        $criteria->addAssociation('seoUrls');
        $products = $this->productRepository->search($criteria, $context);

        /** @var ProductEntity $validProduct */
        $validProduct = $products->filterByProperty('id', $id)->first();

        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $validProduct->getExtension('seoUrls');

        $validSeoUrls = $seoUrls->filterByProperty('isValid', true);
        static::assertCount(1, $validSeoUrls);
        static::assertEquals($id, $validSeoUrls->first()->getForeignKey());

        $invalidSeoUrls = $seoUrls->filterByProperty('isValid', false);
        static::assertCount(0, $invalidSeoUrls);

        /** @var ProductEntity $invalidProduct */
        $invalidProduct = $products->filterByProperty('id', $dupId)->first();

        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $invalidProduct->getExtension('seoUrls');

        $validSeoUrls = $seoUrls->filterByProperty('isValid', true);
        static::assertCount(0, $validSeoUrls);

        $invalidSeoUrls = $seoUrls->filterByProperty('isValid', false);
        static::assertCount(1, $invalidSeoUrls);
        static::assertEquals($dupId, $invalidSeoUrls->first()->getForeignKey());
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
            'entityName' => ProductDefinition::getEntityName(),
            'routeName' => ProductDetailPageSeoUrlIndexer::ROUTE_NAME,
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

        $languages = [];
        foreach ($languageIds as $langId) {
            $languages[] = ['id' => $langId];
        }

        $paymentMethod = $this->getValidPaymentMethodId();
        $shippingMethod = $this->getValidShippingMethodId();
        $country = $this->getValidCountryId();

        $repo->upsert([[
            'id' => $id,
            'name' => $name,
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'accessKey' => 'foobar',
            'secretAccessKey' => 'foobar',
            'languageId' => $defaultLanguageId,
            'snippetSetId' => $this->getSnippetSetIdForLocale('en_GB'),
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
        ]], Context::createDefaultContext());

        return $repo->search(new Criteria([$id]), Context::createDefaultContext())->first();
    }
}
