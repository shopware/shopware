<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\GoogleShopping\Client\Adapter\GoogleShoppingContentAccountResource;
use Shopware\Core\Content\GoogleShopping\Service\GoogleShoppingMerchantAccount;
use Shopware\Core\Content\Test\GoogleShopping\GoogleShoppingIntegration;
use Shopware\Core\Framework\Context;
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
    private $googleShoppingContentAccountResource;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        skipTestNext6050($this);
        $this->repository = $this->getContainer()->get('google_shopping_merchant_account.repository');
        $this->context = Context::createDefaultContext();
        $this->googleShoppingContentAccountResource = $this->createMock(GoogleShoppingContentAccountResource::class);
    }

    public function testGetInfo(): void
    {
        $merchantId = Uuid::randomHex();

        $this->googleShoppingContentAccountResource->expects(static::once())->method('get')->with($merchantId)->willReturn(['storeName' => 'John Doe Store']);

        $merchantAccountService = new GoogleShoppingMerchantAccount($this->repository, $this->googleShoppingContentAccountResource);

        $merchantInfo = $merchantAccountService->getInfo($merchantId);

        static::assertEquals(['storeName' => 'John Doe Store'], $merchantInfo);
    }

    public function testList(): void
    {
        $this->googleShoppingContentAccountResource->expects(static::once())->method('list')->willReturn([
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

        $merchantAccountService = new GoogleShoppingMerchantAccount($this->repository, $this->googleShoppingContentAccountResource);

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

        $merchantAccountService = new GoogleShoppingMerchantAccount($this->repository, $this->googleShoppingContentAccountResource);

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

        $merchantAccountService = new GoogleShoppingMerchantAccount($this->repository, $this->googleShoppingContentAccountResource);

        $merchantAccountService->delete($merchantId, $this->context);

        $account = $this->repository->search($criteria, $this->context);

        static::assertEquals(0, $account->count());
    }
}
