<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Seo\HreflangLoaderInterface;
use Shopware\Core\Content\Seo\HreflangLoaderParameter;
use Shopware\Core\Content\Test\TestProductSeoUrlRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tax\TaxEntity;
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

    /**
     * @var EntityRepository<LanguageCollection>
     */
    private EntityRepository $languageRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDefaultSalesChannelDomain();

        $this->seoUrlRepository = $this->getContainer()->get('seo_url.repository');
        $this->salesChannelDomainRepository = $this->getContainer()->get('sales_channel_domain.repository');
        $this->languageRepository = $this->getContainer()->get('language.repository');

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

        static::assertEquals(0, $links->count());
    }

    public function testProductWithOnlyOneDomain(): void
    {
        $productId = Uuid::randomHex();

        $languageId = $this->languageRepository->searchIds(new Criteria(), $this->salesChannelContext->getContext())->firstId();
        static::assertNotNull($languageId);

        $domain = new SalesChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl('https://test.de');
        $domain->setHreflangUseOnlyLocale(false);
        $domain->setLanguageId($languageId);

        static::assertInstanceOf(SalesChannelDomainCollection::class, $this->salesChannelContext->getSalesChannel()->getDomains());
        $this->salesChannelContext->getSalesChannel()->getDomains()->add($domain);
        $firstDomain = $this->salesChannelContext->getSalesChannel()->getDomains()->first();
        static::assertInstanceOf(SalesChannelDomainEntity::class, $firstDomain);

        $this->seoUrlRepository->create([
            [
                'id' => Uuid::randomHex(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'languageId' => $firstDomain->getLanguageId(),
                'routeName' => TestProductSeoUrlRoute::ROUTE_NAME,
                'foreignKey' => $productId,
                'pathInfo' => '/test/' . $productId,
                'seoPathInfo' => '/test-path',
            ],
        ], $this->salesChannelContext->getContext());

        $links = $this->hreflangLoader->load($this->createParameter($productId));
        static::assertEquals(0, $links->count());
    }

    public function testProductWithTwoDomains(): void
    {
        $this->salesChannelContext->getSalesChannel()->setHreflangActive(true);

        $productId = Uuid::randomHex();

        list($first, $last) = $this->getFirstAndLastLanguages();

        $this->salesChannelDomainRepository->create([
            [
                'url' => 'https://test.de',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $first->getId(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
                'currencyId' => Defaults::CURRENCY,
            ],
            [
                'url' => 'https://test.de/en',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $last->getId(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->salesChannelContext->getContext());

        $this->seoUrlRepository->create([
            [
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'languageId' => $first->getId(),
                'routeName' => TestProductSeoUrlRoute::ROUTE_NAME,
                'foreignKey' => $productId,
                'pathInfo' => '/test/' . $productId,
                'seoPathInfo' => 'test-path',
                'isCanonical' => true,
            ],
            [
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'languageId' => $last->getId(),
                'routeName' => TestProductSeoUrlRoute::ROUTE_NAME,
                'foreignKey' => $productId,
                'pathInfo' => '/test/' . $productId,
                'seoPathInfo' => 'test-path',
                'isCanonical' => true,
            ],
        ], $this->salesChannelContext->getContext());

        $links = $this->hreflangLoader->load($this->createParameter($productId));

        static::assertEquals(2, $links->count());
        $foundLinks = 0;

        static::assertInstanceOf(LocaleEntity::class, $first->getLocale());
        static::assertInstanceOf(LocaleEntity::class, $last->getLocale());

        foreach ($links->getElements() as $element) {
            if ($element->getLocale() === $first->getLocale()->getCode()) {
                static::assertEquals('https://test.de/test-path', $element->getUrl());
                ++$foundLinks;
            }

            if ($element->getLocale() === $last->getLocale()->getCode()) {
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

        list($first, $last) = $this->getFirstAndLastLanguages();

        $defaultDomainId = Uuid::randomHex();
        $this->salesChannelContext->getSalesChannel()->setHreflangDefaultDomainId($defaultDomainId);

        $this->salesChannelDomainRepository->create([
            [
                'id' => $defaultDomainId,
                'url' => 'https://test.de',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $first->getId(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
                'currencyId' => Defaults::CURRENCY,
            ],
            [
                'url' => 'https://test.de/en',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $last->getId(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->salesChannelContext->getContext());

        $this->seoUrlRepository->create([
            [
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'languageId' => $first->getId(),
                'routeName' => TestProductSeoUrlRoute::ROUTE_NAME,
                'foreignKey' => $productId,
                'pathInfo' => '/test/' . $productId,
                'seoPathInfo' => 'test-path',
                'isCanonical' => true,
            ],
            [
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'languageId' => $last->getId(),
                'routeName' => TestProductSeoUrlRoute::ROUTE_NAME,
                'foreignKey' => $productId,
                'pathInfo' => '/test/' . $productId,
                'seoPathInfo' => 'test-path',
                'isCanonical' => true,
            ],
        ], $this->salesChannelContext->getContext());

        $links = $this->hreflangLoader->load($this->createParameter($productId));

        static::assertEquals(3, $links->count());

        $foundLinks = 0;

        static::assertInstanceOf(LocaleEntity::class, $first->getLocale());
        static::assertInstanceOf(LocaleEntity::class, $last->getLocale());

        foreach ($links->getElements() as $element) {
            if ($element->getLocale() === $first->getLocale()->getCode()) {
                static::assertEquals('https://test.de/test-path', $element->getUrl());
                ++$foundLinks;
            }

            if ($element->getLocale() === $last->getLocale()->getCode()) {
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

        list($first, $last) = $this->getFirstAndLastLanguages();

        $defaultDomainId = Uuid::randomHex();
        $this->salesChannelContext->getSalesChannel()->setHreflangDefaultDomainId($defaultDomainId);

        $this->salesChannelDomainRepository->create([
            [
                'id' => $defaultDomainId,
                'url' => 'https://test.de',
                'hreflangUseOnlyLocale' => true,
                'languageId' => $first->getId(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
                'currencyId' => Defaults::CURRENCY,
            ],
            [
                'url' => 'https://test.de/en',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $last->getId(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->salesChannelContext->getContext());

        $this->seoUrlRepository->create([
            [
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'languageId' => $first->getId(),
                'routeName' => TestProductSeoUrlRoute::ROUTE_NAME,
                'foreignKey' => $productId,
                'pathInfo' => '/test/' . $productId,
                'seoPathInfo' => 'test-path',
                'isCanonical' => true,
            ],
            [
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'languageId' => $last->getId(),
                'routeName' => TestProductSeoUrlRoute::ROUTE_NAME,
                'foreignKey' => $productId,
                'pathInfo' => '/test/' . $productId,
                'seoPathInfo' => 'test-path',
                'isCanonical' => true,
            ],
        ], $this->salesChannelContext->getContext());

        $links = $this->hreflangLoader->load($this->createParameter($productId));

        static::assertEquals(3, $links->count());

        $foundLinks = 0;

        static::assertInstanceOf(LocaleEntity::class, $first->getLocale());
        static::assertInstanceOf(LocaleEntity::class, $last->getLocale());

        foreach ($links->getElements() as $element) {
            if ($element->getLocale() === mb_substr((string) $first->getLocale()->getCode(), 0, 2)) {
                static::assertEquals('https://test.de/test-path', $element->getUrl());
                ++$foundLinks;
            }

            if ($element->getLocale() === $last->getLocale()->getCode()) {
                static::assertEquals('https://test.de/en/test-path', $element->getUrl());
                ++$foundLinks;
            }
        }

        static::assertEquals(2, $foundLinks);
    }

    public function testHomePageWithTwoDomains(): void
    {
        $this->salesChannelContext->getSalesChannel()->setHreflangActive(true);

        list($first, $last) = $this->getFirstAndLastLanguages();

        $this->salesChannelDomainRepository->create([
            [
                'url' => 'https://test.de',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $first->getId(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
                'currencyId' => Defaults::CURRENCY,
            ],
            [
                'url' => 'https://test.de/en',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $last->getId(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->salesChannelContext->getContext());

        $links = $this->hreflangLoader->load(
            new HreflangLoaderParameter('frontend.home.page', [], $this->salesChannelContext)
        );

        static::assertEquals(2, $links->count());
        $foundLinks = 0;

        static::assertInstanceOf(LocaleEntity::class, $first->getLocale());
        static::assertInstanceOf(LocaleEntity::class, $last->getLocale());

        foreach ($links->getElements() as $element) {
            if ($element->getLocale() === $first->getLocale()->getCode()) {
                static::assertEquals('https://test.de', $element->getUrl());
                ++$foundLinks;
            }

            if ($element->getLocale() === $last->getLocale()->getCode()) {
                static::assertEquals('https://test.de/en', $element->getUrl());
                ++$foundLinks;
            }
        }

        static::assertEquals(2, $foundLinks);
    }

    public function testHomePageWithTwoDomainsAndDefault(): void
    {
        $this->salesChannelContext->getSalesChannel()->setHreflangActive(true);

        list($first, $last) = $this->getFirstAndLastLanguages();

        $defaultDomainId = Uuid::randomHex();
        $this->salesChannelContext->getSalesChannel()->setHreflangDefaultDomainId($defaultDomainId);

        $this->salesChannelDomainRepository->create([
            [
                'id' => $defaultDomainId,
                'url' => 'https://test.de',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $first->getId(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
                'currencyId' => Defaults::CURRENCY,
            ],
            [
                'url' => 'https://test.de/en',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $last->getId(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->salesChannelContext->getContext());

        $links = $this->hreflangLoader->load(
            new HreflangLoaderParameter('frontend.home.page', [], $this->salesChannelContext)
        );

        static::assertEquals(3, $links->count());
        $foundLinks = 0;

        static::assertInstanceOf(LocaleEntity::class, $first->getLocale());
        static::assertInstanceOf(LocaleEntity::class, $last->getLocale());

        foreach ($links->getElements() as $element) {
            if ($element->getLocale() === $first->getLocale()->getCode()) {
                static::assertEquals('https://test.de', $element->getUrl());
                ++$foundLinks;
            }

            if ($element->getLocale() === $last->getLocale()->getCode()) {
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

    /**
     * @return list<array<string, mixed>>
     */
    private function getProductTestData(SalesChannelContext $salesChannelContext): array
    {
        $first = $salesChannelContext->getTaxRules()->first();
        static::assertInstanceOf(TaxEntity::class, $first);
        $taxId = $first->getId();

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

    /**
     * @return LanguageEntity[]
     */
    private function getFirstAndLastLanguages(): array
    {
        $criteria = new Criteria();
        $criteria->addAssociation('locale');

        $languages = $this->languageRepository->search($criteria, $this->salesChannelContext->getContext())->getEntities();

        $first = $languages->first();
        static::assertInstanceOf(LanguageEntity::class, $first);
        $last = $languages->last();
        static::assertInstanceOf(LanguageEntity::class, $last);

        return [$first, $last];
    }
}
