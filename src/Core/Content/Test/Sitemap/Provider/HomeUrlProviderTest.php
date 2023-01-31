<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Sitemap\Provider;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Sitemap\Provider\HomeUrlProvider;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('sales-channel')]
class HomeUrlProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    protected function setUp(): void
    {
        parent::setUp();

        $contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $contextFactory->create('', TestDefaults::SALES_CHANNEL);
    }

    public function testGetHomeUrlSalesChannelIsExistingTwoDomain(): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('locale');
        $languages = $this->getContainer()->get('language.repository')->search($criteria, $this->salesChannelContext->getContext())->getEntities();

        $domain = new SalesChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl('https://test-sitemap.de');
        $domain->setHreflangUseOnlyLocale(false);
        $domain->setLanguageId($languages->first()->getId());

        $this->salesChannelContext->getSalesChannel()->getDomains()->add($domain);

        $domain = new SalesChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl('https://test-sitemap.de/en');
        $domain->setHreflangUseOnlyLocale(false);
        $domain->setLanguageId($languages->last()->getId());

        $this->salesChannelContext->getSalesChannel()->getDomains()->add($domain);

        $homeUrlProvider = new HomeUrlProvider();

        static::assertCount(1, $homeUrlProvider->getUrls($this->salesChannelContext, 100)->getUrls());
    }

    public function testGetHomeUrlWithSalesChannelIsExistingOneDomain(): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('locale');
        $languages = $this->getContainer()->get('language.repository')->search($criteria, $this->salesChannelContext->getContext())->getEntities();

        $languageId = $this->salesChannelContext->getLanguageId();
        $language = $languages->get($languageId);

        $domain = new SalesChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl('https://test-sitemap.de/en');
        $domain->setHreflangUseOnlyLocale(false);
        $domain->setLanguageId($language->getId());

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
