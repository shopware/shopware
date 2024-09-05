<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Sitemap\Provider;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Sitemap\Provider\HomeUrlProvider;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('services-settings')]
class HomeUrlProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private SalesChannelContext $salesChannelContext;

    /**
     * @var EntityRepository<LanguageCollection>
     */
    private EntityRepository $languageRepository;

    protected function setUp(): void
    {
        $this->languageRepository = $this->getContainer()->get('language.repository');
        $contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $contextFactory->create('', TestDefaults::SALES_CHANNEL);
    }

    public function testGetHomeUrlSalesChannelIsExistingTwoDomain(): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('locale');
        $languages = $this->languageRepository->search($criteria, $this->salesChannelContext->getContext())
            ->getEntities();

        $domain = new SalesChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl('https://test-sitemap.de');
        $domain->setHreflangUseOnlyLocale(false);
        $first = $languages->first();
        static::assertInstanceOf(LanguageEntity::class, $first);
        $domain->setLanguageId($first->getId());

        static::assertInstanceOf(SalesChannelDomainCollection::class, $this->salesChannelContext->getSalesChannel()->getDomains());
        $this->salesChannelContext->getSalesChannel()->getDomains()->add($domain);

        $domain = new SalesChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl('https://test-sitemap.de/en');
        $domain->setHreflangUseOnlyLocale(false);
        $last = $languages->last();
        static::assertInstanceOf(LanguageEntity::class, $last);
        $domain->setLanguageId($last->getId());

        $this->salesChannelContext->getSalesChannel()->getDomains()->add($domain);

        $homeUrlProvider = new HomeUrlProvider();

        static::assertCount(1, $homeUrlProvider->getUrls($this->salesChannelContext, 100)->getUrls());
    }

    public function testGetHomeUrlWithSalesChannelIsExistingOneDomain(): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('locale');
        $languages = $this->languageRepository->search($criteria, $this->salesChannelContext->getContext())
            ->getEntities();

        $languageId = $this->salesChannelContext->getLanguageId();
        $language = $languages->get($languageId);
        static::assertInstanceOf(LanguageEntity::class, $language);

        $domain = new SalesChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl('https://test-sitemap.de/en');
        $domain->setHreflangUseOnlyLocale(false);
        $domain->setLanguageId($language->getId());

        static::assertInstanceOf(SalesChannelDomainCollection::class, $this->salesChannelContext->getSalesChannel()->getDomains());
        $this->salesChannelContext->getSalesChannel()->getDomains()->add($domain);

        $homeUrlProvider = new HomeUrlProvider();

        static::assertCount(1, $homeUrlProvider->getUrls($this->salesChannelContext, 100)->getUrls());
    }

    public function testGetHomeUrlWithSalesChannelHaveNoDomain(): void
    {
        $homeUrlProvider = new HomeUrlProvider();

        $results = $homeUrlProvider->getUrls($this->salesChannelContext, 100);

        static::assertEmpty($results->getUrls()[0]->getLoc());
    }

    public function testProviderNameIsHome(): void
    {
        $homeUrlProvider = new HomeUrlProvider();

        static::assertEquals('home', $homeUrlProvider->getName());
    }
}
