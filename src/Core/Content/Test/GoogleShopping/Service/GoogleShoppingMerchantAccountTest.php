<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\GoogleShopping\Client\Adapter\GoogleShoppingContentAccountResource;
use Shopware\Core\Content\GoogleShopping\Client\Adapter\SiteVerificationResource;
use Shopware\Core\Content\GoogleShopping\Exception\GoogleShoppingException;
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

    protected function setUp(): void
    {
        skipTestNext6050($this);
        $this->repository = $this->getContainer()->get('google_shopping_merchant_account.repository');
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');

        $this->context = Context::createDefaultContext();
        $this->contentAccountResource = $this->createMock(GoogleShoppingContentAccountResource::class);
        $this->siteVerificationResource = $this->createMock(SiteVerificationResource::class);
    }

    public function testGetInfo(): void
    {
        $merchantId = Uuid::randomHex();

        $this->contentAccountResource->expects(static::once())->method('get')->with($merchantId)->willReturn(['storeName' => 'John Doe Store']);

        $merchantAccountService = new GoogleShoppingMerchantAccount($this->repository, $this->contentAccountResource, $this->siteVerificationResource, $this->salesChannelRepository);

        $merchantInfo = $merchantAccountService->getInfo($merchantId);

        static::assertEquals(['storeName' => 'John Doe Store'], $merchantInfo);
    }

    public function testList(): void
    {
        $this->contentAccountResource->expects(static::once())->method('list')->willReturn([
            [
                'name' => 'John Doe Store',
                'location' => 'Germany',
                'id' => 123,
            ],
            [
                'name' => 'Jane Doe Store',
                'location' => 'Iceland',
                'id' => 456,
            ],
            [
                'name' => 'Jim Doe Store',
                'location' => 'Netherland',
                'id' => 789,
            ],
        ]);

        $merchantAccountService = new GoogleShoppingMerchantAccount($this->repository, $this->contentAccountResource, $this->siteVerificationResource, $this->salesChannelRepository);

        $list = $merchantAccountService->list();

        static::assertEquals([
            [
                'name' => 'John Doe Store',
                'id' => 123,
            ], [
                'name' => 'Jane Doe Store',
                'id' => 456,
            ], [
                'name' => 'Jim Doe Store',
                'id' => 789,
            ],
        ], $list);
    }

    public function testCreate(): void
    {
        $googleAccount = $this->createGoogleShoppingAccount(Uuid::randomHex());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('accountId', $googleAccount['id']));

        $account = $this->repository->search($criteria, $this->context);

        static::assertEquals(0, $account->count());

        $merchantAccountService = new GoogleShoppingMerchantAccount($this->repository, $this->contentAccountResource, $this->siteVerificationResource, $this->salesChannelRepository);

        $merchantAccountService->create(Uuid::randomHex(), $googleAccount['id'], $this->context);

        $account = $this->repository->search($criteria, $this->context);

        static::assertEquals(1, $account->count());
    }

    public function testDelete(): void
    {
        $googleAccount = $this->createGoogleShoppingAccount(Uuid::randomHex());

        $merchantId = $this->connectGoogleShoppingMerchantAccount($googleAccount['googleAccount']['id'], Uuid::randomHex());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('accountId', $googleAccount['id']));

        $account = $this->repository->search($criteria, $this->context);

        static::assertEquals(1, $account->count());

        $merchantAccountService = new GoogleShoppingMerchantAccount($this->repository, $this->contentAccountResource, $this->siteVerificationResource, $this->salesChannelRepository);

        $merchantAccountService->delete($merchantId, $this->context);

        $account = $this->repository->search($criteria, $this->context);

        static::assertEquals(0, $account->count());
    }

    public function testGetStatus(): void
    {
        $merchantAccountService = new GoogleShoppingMerchantAccount($this->repository, $this->contentAccountResource, $this->siteVerificationResource, $this->salesChannelRepository);

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

        $merchantAccountService = new GoogleShoppingMerchantAccount($this->repository, $this->contentAccountResource, $this->siteVerificationResource, $this->salesChannelRepository);

        $status = $merchantAccountService->isSiteVerified('https%3A%2F%shopware.test%2F', '123123');

        static::assertTrue($status);
    }

    public function testIsNotSiteVerified(): void
    {
        $this->siteVerificationResource->expects(static::once())->method('get')->with('https%3A%2F%not_shopware.test%2F')->willThrowException(new GoogleShoppingException('Site is not verified', 403));

        $merchantAccountService = new GoogleShoppingMerchantAccount($this->repository, $this->contentAccountResource, $this->siteVerificationResource, $this->salesChannelRepository);

        $status = $merchantAccountService->isSiteVerified('https%3A%2F%not_shopware.test%2F', '123123');

        static::assertFalse($status);
    }
}
