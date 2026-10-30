<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerGroupRegistrationSeoUrlSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private const ROUTE_NAME = 'store-api.customer-group-registration.config';

    /**
     * @var EntityRepository<CustomerGroupCollection>
     */
    private EntityRepository $customerGroupRepository;

    /**
     * @var EntityRepository<SeoUrlCollection>
     */
    private EntityRepository $seoUrlRepository;

    protected function setUp(): void
    {
        $this->customerGroupRepository = static::getContainer()->get('customer_group.repository');
        $this->seoUrlRepository = static::getContainer()->get('seo_url.repository');
    }

    public function testUrlsAreWrittenForHeadlessSalesChannelWithExternalStorefrontDomain(): void
    {
        $salesChannelId = $this->createHeadlessSalesChannel(isExternalStorefront: true);
        $groupId = $this->createRegistrationCustomerGroup($salesChannelId);

        $urls = $this->getHeadlessSeoUrls($groupId);

        static::assertCount(1, $urls);

        $url = $urls->first();

        static::assertNotNull($url);
        static::assertSame($salesChannelId, $url->getSalesChannelId());
        static::assertSame($groupId, $url->getForeignKey());
        static::assertSame(self::ROUTE_NAME, $url->getRouteName());
        static::assertSame('/store-api/customer-group-registration/config/' . $groupId, $url->getPathInfo());
        static::assertSame('test', $url->getSeoPathInfo());
    }

    public function testUrlsAreWrittenWhenDomainBecomesExternalStorefront(): void
    {
        $salesChannelId = $this->createHeadlessSalesChannel(isExternalStorefront: false);
        $groupId = $this->createRegistrationCustomerGroup($salesChannelId);

        static::assertCount(0, $this->getHeadlessSeoUrls($groupId));

        $this->updateDomainExternalStorefront($salesChannelId, true);

        static::assertCount(1, $this->getHeadlessSeoUrls($groupId));
    }

    public function testUrlsAreRemovedWhenDomainStopsBeingExternalStorefront(): void
    {
        $salesChannelId = $this->createHeadlessSalesChannel(isExternalStorefront: true);
        $groupId = $this->createRegistrationCustomerGroup($salesChannelId);

        static::assertCount(1, $this->getHeadlessSeoUrls($groupId));

        $this->updateDomainExternalStorefront($salesChannelId, false);

        static::assertCount(0, $this->getHeadlessSeoUrls($groupId));
    }

    public function testNoUrlsAreWrittenForStorefrontSalesChannels(): void
    {
        $salesChannelId = $this->createSalesChannel()['id'];
        $groupId = $this->createRegistrationCustomerGroup($salesChannelId);

        static::assertCount(0, $this->getHeadlessSeoUrls($groupId));
    }

    private function createHeadlessSalesChannel(bool $isExternalStorefront): string
    {
        return $this->createSalesChannel([
            'typeId' => Defaults::SALES_CHANNEL_TYPE_API,
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://frontend.' . Uuid::randomHex() . '.test',
                    'isExternalStorefront' => $isExternalStorefront,
                ],
            ],
        ])['id'];
    }

    private function createRegistrationCustomerGroup(string $salesChannelId): string
    {
        $id = Uuid::randomHex();

        $this->customerGroupRepository->create([
            [
                'id' => $id,
                'name' => 'Test',
                'registrationActive' => true,
                'registrationTitle' => 'test',
                'registrationSalesChannels' => [['id' => $salesChannelId]],
            ],
        ], Context::createDefaultContext());

        return $id;
    }

    private function updateDomainExternalStorefront(string $salesChannelId, bool $isExternalStorefront): void
    {
        $domainRepository = static::getContainer()->get('sales_channel_domain.repository');

        $domainId = $domainRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('salesChannelId', $salesChannelId)),
            Context::createDefaultContext()
        )->firstId();

        static::assertNotNull($domainId);

        $domainRepository->update([
            [
                'id' => $domainId,
                'isExternalStorefront' => $isExternalStorefront,
            ],
        ], Context::createDefaultContext());
    }

    private function getHeadlessSeoUrls(string $foreignKey): SeoUrlCollection
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('foreignKey', $foreignKey))
            ->addFilter(new EqualsFilter('routeName', self::ROUTE_NAME));

        return $this->seoUrlRepository->search($criteria, Context::createDefaultContext())->getEntities();
    }
}
