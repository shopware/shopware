<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class CustomerGroupSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerGroupRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $seoRepository;

    public function setUp(): void
    {
        $this->customerGroupRepository = $this->getContainer()->get('customer_group.repository');
        $this->seoRepository = $this->getContainer()->get('seo_url.repository');
    }

    public function testUrlsAreNotWritten(): void
    {
        $id = Uuid::randomHex();

        $this->customerGroupRepository->create([
            [
                'id' => $id,
                'name' => 'Test',
            ],
        ], Context::createDefaultContext());

        $urls = $this->getSeoUrlsById($id);

        static::assertSame(0, $urls->count());
    }

    public function testUrlsAreWrittenToOnlyAssignedSalesChannel(): void
    {
        $s1 = $this->createSalesChannel()['id'];

        $id = Uuid::randomHex();

        $this->customerGroupRepository->create([
            [
                'id' => $id,
                'name' => 'Test',
                'registrationActive' => true,
                'registrationTitle' => 'test',
                'registrationSalesChannels' => [['id' => $s1]],
            ],
        ], Context::createDefaultContext());

        $urls = $this->getSeoUrlsById($id);

        static::assertSame(1, $urls->count());

        $url = $urls->first();

        static::assertSame($s1, $url->getSalesChannelId());
        static::assertSame($id, $url->getForeignKey());
        static::assertSame('frontend.account.customer-group-registration.page', $url->getRouteName());
        static::assertSame('test', $url->getSeoPathInfo());
    }

    public function testUrlsAreNotWrittenWhenRegistrationIsDisabled(): void
    {
        $s1 = $this->createSalesChannel()['id'];

        $id = Uuid::randomHex();

        $this->customerGroupRepository->create([
            [
                'id' => $id,
                'name' => 'Test',
                'registrationActive' => false,
                'registrationTitle' => 'test',
                'registrationSalesChannels' => [['id' => $s1]],
            ],
        ], Context::createDefaultContext());

        $urls = $this->getSeoUrlsById($id);

        static::assertSame(0, $urls->count());
    }

    public function testUrlExistsForAllLanguages(): void
    {
        $s1 = $this->createSalesChannel()['id'];

        $languageIds = array_values($this->getContainer()->get('language.repository')->search(new Criteria(), Context::createDefaultContext())->getIds());

        $this->getContainer()->get('sales_channel.repository')->upsert([
            [
                'id' => $s1,
                'languages' => array_map(function (string $id) {
                    return ['id' => $id];
                }, $languageIds),
            ],
        ], Context::createDefaultContext());

        $id = Uuid::randomHex();

        $this->customerGroupRepository->create([
            [
                'id' => $id,
                'name' => 'Test',
                'registrationActive' => true,
                'registrationTitle' => 'test',
                'registrationSalesChannels' => [['id' => $s1]],
            ],
        ], Context::createDefaultContext());

        $urls = $this->getSeoUrlsById($id);

        static::assertSame(\count($languageIds), $urls->count());

        foreach ($languageIds as $languageId) {
            $foundUrl = false;

            foreach ($urls->getElements() as $url) {
                if ($url->getLanguageId() === $languageId) {
                    static::assertSame('test', $url->getSeoPathInfo());
                    static::assertSame($s1, $url->getSalesChannelId());
                    $foundUrl = true;
                }
            }

            static::assertTrue($foundUrl, sprintf('Cannot find url for language "%s"', $languageId));
        }
    }

    public function testCreatedUrlsAreDeletedWhenGroupIsDeleted(): void
    {
        $s1 = $this->createSalesChannel()['id'];

        $id = Uuid::randomHex();

        $this->customerGroupRepository->create([
            [
                'id' => $id,
                'name' => 'Test',
                'registrationActive' => true,
                'registrationTitle' => 'test',
                'registrationSalesChannels' => [['id' => $s1]],
            ],
        ], Context::createDefaultContext());

        static::assertSame(1, $this->getSeoUrlsById($id)->count());

        $this->customerGroupRepository->delete([['id' => $id]], Context::createDefaultContext());

        static::assertSame(0, $this->getSeoUrlsById($id)->count());
    }

    public function testSaveGroupAndEnableLaterSalesChannels(): void
    {
        $s1 = $this->createSalesChannel()['id'];

        $id = Uuid::randomHex();

        $this->customerGroupRepository->create([
            [
                'id' => $id,
                'name' => 'Test',
                'registrationActive' => true,
                'registrationTitle' => 'test',
            ],
        ], Context::createDefaultContext());

        $this->customerGroupRepository->upsert([
            [
                'id' => $id,
                'registrationSalesChannels' => [['id' => $s1]],
            ],
        ], Context::createDefaultContext());

        $urls = $this->getSeoUrlsById($id);

        static::assertSame(1, $urls->count());

        $url = $urls->first();

        static::assertSame($s1, $url->getSalesChannelId());
        static::assertSame($id, $url->getForeignKey());
        static::assertSame('frontend.account.customer-group-registration.page', $url->getRouteName());
        static::assertSame('test', $url->getSeoPathInfo());
    }

    private function getSeoUrlsById(string $id): SeoUrlCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('foreignKey', $id));

        return $this->seoRepository->search($criteria, Context::createDefaultContext())->getEntities();
    }

    private function createSalesChannel(array $salesChannelOverride = []): array
    {
        /** @var EntityRepositoryInterface $salesChannelRepository */
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $paymentMethod = $this->getAvailablePaymentMethod();
        $salesChannel = array_merge([
            'id' => Uuid::randomHex(),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'name' => 'API Test case sales channel',
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => $paymentMethod->getId(),
            'paymentMethods' => [['id' => $paymentMethod->getId()]],
            'shippingMethodId' => $this->getAvailableShippingMethod()->getId(),
            'navigationCategoryId' => $this->getValidCategoryId(),
            'countryId' => $this->getValidCountryId(),
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://localhost/' . Uuid::randomHex(),
                ],
            ],
        ], $salesChannelOverride);

        $salesChannelRepository->upsert([$salesChannel], Context::createDefaultContext());

        return $salesChannel;
    }
}
