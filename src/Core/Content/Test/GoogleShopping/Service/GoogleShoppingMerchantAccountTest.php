<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\GoogleShopping\Client\Adapter\GoogleShoppingContentAccountResource;
use Shopware\Core\Content\GoogleShopping\Client\Adapter\SiteVerificationResource;
use Shopware\Core\Content\GoogleShopping\Exception\GoogleShoppingException;
use Shopware\Core\Content\GoogleShopping\GoogleShoppingAccountEntity;
use Shopware\Core\Content\GoogleShopping\Service\GoogleShoppingMerchantAccount;
use Shopware\Core\Content\Test\GoogleShopping\GoogleShoppingIntegration;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use function Flag\skipTestNext6050;

class GoogleShoppingMerchantAccountTest extends TestCase
{
    use IntegrationTestBehaviour;
    use GoogleShoppingIntegration;

    /**
     * @var \Shopware\Core\Framework\DataAbstractionLayer\EntityRepository
     */
    private $repository;

    /**
     * @var MockObject
     */
    private $contentAccountResource;

    /**
     * @var GoogleShoppingMerchantAccount
     */
    private $merchantAccountService;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var MockObject|SiteVerificationResource
     */
    private $siteVerificationResource;

    /**
     * @var EntityRepositoryInterface|null
     */
    private $salesChannelRepository;

    /**
     * @var EntityRepositoryInterface|null
     */
    private $googleAccountRepository;

    protected function setUp(): void
    {
        skipTestNext6050($this);
        $this->getMockGoogleClient();
        $this->repository = $this->getContainer()->get('google_shopping_merchant_account.repository');
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $this->context = Context::createDefaultContext();
        $this->contentAccountResource = $this->createMock(GoogleShoppingContentAccountResource::class);
        $this->siteVerificationResource = $this->createMock(SiteVerificationResource::class);
        $this->googleAccountRepository = $this->getContainer()->get('google_shopping_account.repository');
        $this->merchantAccountService = $this->getContainer()->get(GoogleShoppingMerchantAccount::class);
    }

    public function testGetInfo(): void
    {
        $merchantId = Uuid::randomHex();

        $merchantInfo = $this->merchantAccountService->getInfo($merchantId);

        static::assertEquals([
            'adultContent' => false,
            'id' => '12345678',
            'kind' => 'content#account',
            'name' => 'Test merchant',
            'websiteUrl' => "http:\/\/shopware.test",
        ], $merchantInfo);
    }

    public function testList(): void
    {
        $list = $this->merchantAccountService->list();

        static::assertNotEmpty($list);

        foreach ($list as $account) {
            static::assertArrayHasKey('id', $account);
            static::assertArrayHasKey('name', $account);
        }
    }

    public function testCreate(): void
    {
        $googleAccount = $this->createGoogleShoppingAccount(Uuid::randomHex());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('accountId', $googleAccount['id']));

        $account = $this->repository->search($criteria, $this->context);

        static::assertEquals(0, $account->count());

        $merchantAccountService = $this->getGoogleShoppingMerchantAccountService();

        $merchantAccountService->create(Uuid::randomHex(), $googleAccount['id'], $this->context);

        $account = $this->repository->search($criteria, $this->context);

        static::assertEquals(1, $account->count());
    }

    public function testUnassign(): void
    {
        $googleAccount = $this->createGoogleShoppingAccount(Uuid::randomHex());

        $merchantId = $this->connectGoogleShoppingMerchantAccount($googleAccount['googleAccount']['id'], Uuid::randomHex());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('accountId', $googleAccount['id']));

        $account = $this->repository->search($criteria, $this->context);

        static::assertEquals(1, $account->count());

        $merchantAccountService = $this->getGoogleShoppingMerchantAccountService();

        $merchantAccountService->unassign($googleAccount['id'], $merchantId, $this->context);

        $merchantAccount = $this->repository->search($criteria, $this->context);

        static::assertEquals(0, $merchantAccount->count());

        $criteria = new Criteria([$googleAccount['id']]);

        /** @var GoogleShoppingAccountEntity $account */
        $account = $this->googleAccountRepository->search($criteria, $this->context)->get($googleAccount['id']);

        static::assertNotEmpty($account);
        static::assertNull($account->getTosAcceptedAt());
    }

    public function testGetStatus(): void
    {
        $status = $this->merchantAccountService->getStatus('123456789');

        static::assertNotEmpty($status);
        static::assertArrayHasKey('accountLevelIssues', $status);
        static::assertArrayHasKey('isSuspended', $status);
        static::assertArrayHasKey('accountId', $status);
        static::assertArrayHasKey('websiteClaimed', $status);

        $this->contentAccountResource->expects(static::exactly(3))->method('getStatus')->willReturnMap([
            [
                'account_with_no_error',
                'account_with_no_error',
                [
                    'accountId' => 1,
                ],
            ],
            [
                'account_without_critical_error',
                'account_without_critical_error',
                [
                    'accountId' => 2,
                    'accountLevelIssues' => [
                        [
                            'documentation' => "https:\/\/support.google.com\/merchants\/answer\/6159060",
                            'id' => 'missing_ad_words_link',
                            'severity' => 'error',
                            'title' => 'Pending Google Ads account link request',
                        ],
                    ],
                ],
            ],
            [
                'account_with_critical_error',
                'account_with_critical_error',
                [
                    'accountId' => 3,
                    'accountLevelIssues' => [
                        [
                            'documentation' => "https:\/\/support.google.com\/merchants\/answer\/176793",
                            'id' => 'home_page_issue',
                            'severity' => 'critical',
                            'title' => 'Website not claimed',
                        ],
                        [
                            'documentation' => "https:\/\/support.google.com\/merchants\/answer\/6159060",
                            'id' => 'missing_ad_words_link',
                            'severity' => 'error',
                            'title' => 'Pending Google Ads account link request',
                        ],
                    ],
                ],
            ],
        ]);

        $merchantAccountService = $this->getGoogleShoppingMerchantAccountService();

        $status = $merchantAccountService->getStatus('account_with_no_error');

        static::assertNotEmpty($status);
        static::assertFalse($status['isSuspended']);

        $status = $merchantAccountService->getStatus('account_without_critical_error');

        static::assertFalse($status['isSuspended']);

        $status = $merchantAccountService->getStatus('account_with_critical_error');

        static::assertTrue($status['isSuspended']);
    }

    public function testIsSiteVerified(): void
    {
        $this->siteVerificationResource->expects(static::once())->method('get')->with('https%3A%2F%shopware.test%2F')->willReturn([
            'id' => 'https%3A%2F%shopware.test%2F',
            'owners' => [
                'john.doe@example.com',
                'jane.doe@example.com',
            ],
            'site' => [
                'identifier' => 'http://shopware.test/',
                'type' => 'SITE',
            ],
        ]);

        $merchantAccountService = $this->getGoogleShoppingMerchantAccountService();

        $status = $merchantAccountService->isSiteVerified('https%3A%2F%shopware.test%2F', '123123');

        static::assertTrue($status);
    }

    public function testIsNotSiteVerified(): void
    {
        $this->siteVerificationResource->expects(static::once())->method('get')->with('https%3A%2F%not_shopware.test%2F')->willThrowException(new GoogleShoppingException('Site is not verified', 403));

        $merchantAccountService = $this->getGoogleShoppingMerchantAccountService();

        $status = $merchantAccountService->isSiteVerified('https%3A%2F%not_shopware.test%2F', '123123');

        static::assertFalse($status);
    }

    private function getGoogleShoppingMerchantAccountService(): GoogleShoppingMerchantAccount
    {
        return new GoogleShoppingMerchantAccount(
            $this->repository,
            $this->contentAccountResource,
            $this->siteVerificationResource,
            $this->salesChannelRepository,
            $this->googleAccountRepository
        );
    }
}
