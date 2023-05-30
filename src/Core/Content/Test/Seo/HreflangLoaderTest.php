<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Seo\Hreflang\HreflangCollection;
use Shopware\Core\Content\Seo\HreflangLoaderInterface;
use Shopware\Core\Content\Seo\HreflangLoaderParameter;
use Shopware\Core\Content\Test\TestProductSeoUrlRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class HreflangLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $seoUrlRepository;

    private EntityRepository $salesChannelDomainRepository;

    private SalesChannelContext $salesChannelContext;

    private HreflangLoaderInterface $hreflangLoader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDefaultSalesChannelDomain();

        $this->seoUrlRepository = $this->getContainer()->get('seo_url.repository');

        $this->salesChannelDomainRepository = $this->getContainer()->get('sales_channel_domain.repository');

        $contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $contextFactory->create('', TestDefaults::SALES_CHANNEL);

        $this->hreflangLoader = $this->getContainer()->get(HreflangLoaderInterface::class);

        $this->createProducts();
    }

    public function testDisable(): void
    {
        $randomProduct = $this->getContainer()->get('product.repository')->searchIds(new Criteria(), $this->salesChannelContext->getContext());
        $this->salesChannelContext->getSalesChannel()->setHreflangActive(false);

        $randomId = $randomProduct->firstId();
        static::assertNotNull($randomId);
        $links = $this->hreflangLoader->load($this->createParameter($randomId));

        static::assertInstanceOf(HreflangCollection::class, $links);
        static::assertEquals(0, $links->count());
    }

    public function testProductWithOnlyOneDomain(): void
    {
        $productId = Uuid::randomHex();

        $languageId = $this->getContainer()->get('language.repository')->searchIds(new Criteria(), $this->salesChannelContext->getContext())->firstId();
        static::assertNotNull($languageId);

        $domain = new SalesChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl('https://test.de');
        $domain->setHreflangUseOnlyLocale(false);
        $domain->setLanguageId($languageId);

        $this->salesChannelContext->getSalesChannel()->getDomains()->add($domain);

        $this->seoUrlRepository->create([
            [
                'id' => Uuid::randomHex(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'languageId' => $this->salesChannelContext->getSalesChannel()->getDomains()->first()->getLanguageId(),
                'routeName' => TestProductSeoUrlRoute::ROUTE_NAME,
                'foreignKey' => $productId,
                'pathInfo' => '/test/' . $productId,
                'seoPathInfo' => '/test-path',
            ],
        ], $this->salesChannelContext->getContext());

        $links = $this->hreflangLoader->load($this->createParameter($productId));
        static::assertInstanceOf(HreflangCollection::class, $links);
        static::assertEquals(0, $links->count());
    }

    public function testProductWithTwoDomains(): void
    {
        $this->salesChannelContext->getSalesChannel()->setHreflangActive(true);

        $productId = Uuid::randomHex();

        $criteria = new Criteria();
        $criteria->addAssociation('locale');
        $languages = $this->getContainer()->get('language.repository')->search($criteria, $this->salesChannelContext->getContext())->getEntities();

        $this->salesChannelDomainRepository->create([
            [
                'url' => 'https://test.de',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $languages->first()->getId(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
                'currencyId' => Defaults::CURRENCY,
            ],
            [
                'url' => 'https://test.de/en',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $languages->last()->getId(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->salesChannelContext->getContext());

        $this->seoUrlRepository->create([
            [
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'languageId' => $languages->first()->getId(),
                'routeName' => TestProductSeoUrlRoute::ROUTE_NAME,
                'foreignKey' => $productId,
                'pathInfo' => '/test/' . $productId,
                'seoPathInfo' => 'test-path',
                'isCanonical' => true,
            ],
            [
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'languageId' => $languages->last()->getId(),
                'routeName' => TestProductSeoUrlRoute::ROUTE_NAME,
                'foreignKey' => $productId,
                'pathInfo' => '/test/' . $productId,
                'seoPathInfo' => 'test-path',
                'isCanonical' => true,
            ],
        ], $this->salesChannelContext->getContext());

        $links = $this->hreflangLoader->load($this->createParameter($productId));

        static::assertInstanceOf(HreflangCollection::class, $links);
        static::assertEquals(2, $links->count());
        $foundLinks = 0;

        foreach ($links->getElements() as $element) {
            if ($element->getLocale() === $languages->first()->getLocale()->getCode()) {
                static::assertEquals('https://test.de/test-path', $element->getUrl());
                ++$foundLinks;
            }

            if ($element->getLocale() === $languages->last()->getLocale()->getCode()) {
                static::assertEquals('https://test.de/en/test-path', $element->getUrl());
                ++$foundLinks;
            }
        }

        static::assertEquals(2, $foundLinks);
    }

    public function testProductWithTwoDomainsWithDefault(): void
    {
        $this->salesChannelContext->getSalesChannel()->setHreflangActive(true);

        $productId = Uuid::randomHex();

        $criteria = new Criteria();
        $criteria->addAssociation('locale');
        $languages = $this->getContainer()->get('language.repository')->search($criteria, $this->salesChannelContext->getContext())->getEntities();

        $defaultDomainId = Uuid::randomHex();
        $this->salesChannelContext->getSalesChannel()->setHreflangDefaultDomainId($defaultDomainId);

        $this->salesChannelDomainRepository->create([
            [
                'id' => $defaultDomainId,
                'url' => 'https://test.de',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $languages->first()->getId(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
                'currencyId' => Defaults::CURRENCY,
            ],
            [
                'url' => 'https://test.de/en',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $languages->last()->getId(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->salesChannelContext->getContext());

        $this->seoUrlRepository->create([
            [
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'languageId' => $languages->first()->getId(),
                'routeName' => TestProductSeoUrlRoute::ROUTE_NAME,
                'foreignKey' => $productId,
                'pathInfo' => '/test/' . $productId,
                'seoPathInfo' => 'test-path',
                'isCanonical' => true,
            ],
            [
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'languageId' => $languages->last()->getId(),
                'routeName' => TestProductSeoUrlRoute::ROUTE_NAME,
                'foreignKey' => $productId,
                'pathInfo' => '/test/' . $productId,
                'seoPathInfo' => 'test-path',
                'isCanonical' => true,
            ],
        ], $this->salesChannelContext->getContext());

        $links = $this->hreflangLoader->load($this->createParameter($productId));

        static::assertInstanceOf(HreflangCollection::class, $links);
        static::assertEquals(3, $links->count());

        $foundLinks = 0;

        foreach ($links->getElements() as $element) {
            if ($element->getLocale() === $languages->first()->getLocale()->getCode()) {
                static::assertEquals('https://test.de/test-path', $element->getUrl());
                ++$foundLinks;
            }

            if ($element->getLocale() === $languages->last()->getLocale()->getCode()) {
                static::assertEquals('https://test.de/en/test-path', $element->getUrl());
                ++$foundLinks;
            }

            if ($element->getLocale() === 'x-default') {
                static::assertEquals('https://test.de/test-path', $element->getUrl());
                ++$foundLinks;
            }
        }

        static::assertEquals(3, $foundLinks);
    }

    public function testProductWithTwoDomainsFirstOnlyLocale(): void
    {
        $this->salesChannelContext->getSalesChannel()->setHreflangActive(true);

        $productId = Uuid::randomHex();

        $criteria = new Criteria();
        $criteria->addAssociation('locale');
        $languages = $this->getContainer()->get('language.repository')->search($criteria, $this->salesChannelContext->getContext())->getEntities();

        $defaultDomainId = Uuid::randomHex();
        $this->salesChannelContext->getSalesChannel()->setHreflangDefaultDomainId($defaultDomainId);

        $this->salesChannelDomainRepository->create([
            [
                'id' => $defaultDomainId,
                'url' => 'https://test.de',
                'hreflangUseOnlyLocale' => true,
                'languageId' => $languages->first()->getId(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
                'currencyId' => Defaults::CURRENCY,
            ],
            [
                'url' => 'https://test.de/en',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $languages->last()->getId(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->salesChannelContext->getContext());

        $this->seoUrlRepository->create([
            [
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'languageId' => $languages->first()->getId(),
                'routeName' => TestProductSeoUrlRoute::ROUTE_NAME,
                'foreignKey' => $productId,
                'pathInfo' => '/test/' . $productId,
                'seoPathInfo' => 'test-path',
                'isCanonical' => true,
            ],
            [
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'languageId' => $languages->last()->getId(),
                'routeName' => TestProductSeoUrlRoute::ROUTE_NAME,
                'foreignKey' => $productId,
                'pathInfo' => '/test/' . $productId,
                'seoPathInfo' => 'test-path',
                'isCanonical' => true,
            ],
        ], $this->salesChannelContext->getContext());

        $links = $this->hreflangLoader->load($this->createParameter($productId));

        static::assertInstanceOf(HreflangCollection::class, $links);
        static::assertEquals(3, $links->count());

        $foundLinks = 0;

        foreach ($links->getElements() as $element) {
            if ($element->getLocale() === mb_substr((string) $languages->first()->getLocale()->getCode(), 0, 2)) {
                static::assertEquals('https://test.de/test-path', $element->getUrl());
                ++$foundLinks;
            }

            if ($element->getLocale() === $languages->last()->getLocale()->getCode()) {
                static::assertEquals('https://test.de/en/test-path', $element->getUrl());
                ++$foundLinks;
            }
        }

        static::assertEquals(2, $foundLinks);
    }

    public function testHomePageWithTwoDomains(): void
    {
        $this->salesChannelContext->getSalesChannel()->setHreflangActive(true);

        $criteria = new Criteria();
        $criteria->addAssociation('locale');
        $languages = $this->getContainer()->get('language.repository')->search($criteria, $this->salesChannelContext->getContext())->getEntities();

        $this->salesChannelDomainRepository->create([
            [
                'url' => 'https://test.de',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $languages->first()->getId(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
                'currencyId' => Defaults::CURRENCY,
            ],
            [
                'url' => 'https://test.de/en',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $languages->last()->getId(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->salesChannelContext->getContext());

        $links = $this->hreflangLoader->load(
            new HreflangLoaderParameter('frontend.home.page', [], $this->salesChannelContext)
        );

        static::assertInstanceOf(HreflangCollection::class, $links);
        static::assertEquals(2, $links->count());
        $foundLinks = 0;

        foreach ($links->getElements() as $element) {
            if ($element->getLocale() === $languages->first()->getLocale()->getCode()) {
                static::assertEquals('https://test.de', $element->getUrl());
                ++$foundLinks;
            }

            if ($element->getLocale() === $languages->last()->getLocale()->getCode()) {
                static::assertEquals('https://test.de/en', $element->getUrl());
                ++$foundLinks;
            }
        }

        static::assertEquals(2, $foundLinks);
    }

    public function testHomePageWithTwoDomainsAndDefault(): void
    {
        $this->salesChannelContext->getSalesChannel()->setHreflangActive(true);

        $criteria = new Criteria();
        $criteria->addAssociation('locale');
        $languages = $this->getContainer()->get('language.repository')->search($criteria, $this->salesChannelContext->getContext())->getEntities();

        $defaultDomainId = Uuid::randomHex();
        $this->salesChannelContext->getSalesChannel()->setHreflangDefaultDomainId($defaultDomainId);

        $this->salesChannelDomainRepository->create([
            [
                'id' => $defaultDomainId,
                'url' => 'https://test.de',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $languages->first()->getId(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
                'currencyId' => Defaults::CURRENCY,
            ],
            [
                'url' => 'https://test.de/en',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $languages->last()->getId(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->salesChannelContext->getContext());

        $links = $this->hreflangLoader->load(
            new HreflangLoaderParameter('frontend.home.page', [], $this->salesChannelContext)
        );

        static::assertInstanceOf(HreflangCollection::class, $links);
        static::assertEquals(3, $links->count());
        $foundLinks = 0;

        foreach ($links->getElements() as $element) {
            if ($element->getLocale() === $languages->first()->getLocale()->getCode()) {
                static::assertEquals('https://test.de', $element->getUrl());
                ++$foundLinks;
            }

            if ($element->getLocale() === $languages->last()->getLocale()->getCode()) {
                static::assertEquals('https://test.de/en', $element->getUrl());
                ++$foundLinks;
            }

            if ($element->getLocale() === 'x-default') {
                static::assertEquals('https://test.de', $element->getUrl());
                ++$foundLinks;
            }
        }

        static::assertEquals(3, $foundLinks);
    }

    private function createParameter(string $productId): HreflangLoaderParameter
    {
        return new HreflangLoaderParameter(TestProductSeoUrlRoute::ROUTE_NAME, [
            'productId' => $productId,
        ], $this->salesChannelContext);
    }

    private function cleanDefaultSalesChannelDomain(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $connection->delete(SalesChannelDomainDefinition::ENTITY_NAME, [
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
        ]);
    }

    private function createProducts(): void
    {
        $products = $this->getProductTestData($this->salesChannelContext);

        $this->getContainer()->get('product.repository')->create($products, $this->salesChannelContext->getContext());
    }

    private function getProductTestData(SalesChannelContext $salesChannelContext): array
    {
        $taxId = $salesChannelContext->getTaxRules()->first()->getId();

        $products = [
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 100,
                'name' => 'test product 1',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'tax' => ['id' => $taxId],
                'manufacturer' => ['name' => 'test'],
                'visibilities' => [
                    ['salesChannelId' => $salesChannelContext->getSalesChannel()->getId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 100,
                'name' => 'test product 2',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'tax' => ['id' => $taxId],
                'manufacturer' => ['name' => 'test'],
                'visibilities' => [
                    ['salesChannelId' => $salesChannelContext->getSalesChannel()->getId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 100,
                'name' => 'test product 3',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'tax' => ['id' => $taxId],
                'manufacturer' => ['name' => 'test'],
                'visibilities' => [
                    ['salesChannelId' => $salesChannelContext->getSalesChannel()->getId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 100,
                'name' => 'test product 4',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'tax' => ['id' => $taxId],
                'manufacturer' => ['name' => 'test'],
                'visibilities' => [
                    ['salesChannelId' => $salesChannelContext->getSalesChannel()->getId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 100,
                'name' => 'test product 5',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'tax' => ['id' => $taxId],
                'manufacturer' => ['name' => 'test'],
                'visibilities' => [
                    ['salesChannelId' => $salesChannelContext->getSalesChannel()->getId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ];

        return $products;
    }
}
