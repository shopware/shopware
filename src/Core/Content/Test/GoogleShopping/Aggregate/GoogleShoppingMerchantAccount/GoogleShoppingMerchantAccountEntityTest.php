<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping\Aggregate\GoogleShoppingMerchantAccount;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\GoogleShopping\Aggregate\GoogleShoppingMerchantAccount\GoogleShoppingMerchantAccountEntity;
use Shopware\Core\Content\Test\GoogleShopping\GoogleShoppingIntegration;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use function Flag\skipTestNext6050;

class GoogleShoppingMerchantAccountEntityTest extends TestCase
{
    use IntegrationTestBehaviour;
    use GoogleShoppingIntegration;

    /** @var EntityRepository */
    private $repository;

    /** @var Context */
    private $context;

    protected function setUp(): void
    {
        skipTestNext6050($this);

        $this->repository = $this->getContainer()->get('google_shopping_merchant_account.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testCreateAccount(): void
    {
        $id = Uuid::randomHex();
        $merchantId = Uuid::randomHex();

        list($accountId) = $this->createGoogleShoppingMerchantAccount($id, $merchantId);

        /** @var GoogleShoppingMerchantAccountEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->get($id);

        static::assertEquals($accountId, $entity->getAccountId());
        static::assertEquals($merchantId, $entity->getMerchantId());
        static::assertEquals($id, $entity->getId());
    }

    public function testUpdateAccount(): void
    {
        $id = Uuid::randomHex();
        $merchantId = Uuid::randomHex();

        list($accountId) = $this->createGoogleShoppingMerchantAccount($id, $merchantId);

        $merchantIdEdited = Uuid::randomHex();

        $googleMerchantAccount = [
            [
                'id' => $id,
                'accountId' => $accountId,
                'merchantId' => $merchantIdEdited,
            ],
        ];

        $this->repository->update($googleMerchantAccount, $this->context);

        /** @var GoogleShoppingMerchantAccountEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->get($id);

        static::assertEquals($accountId, $entity->getAccountId());
        static::assertEquals($merchantIdEdited, $entity->getMerchantId());
        static::assertEquals($id, $entity->getId());
    }

    public function testUpdateAccountForeignKeyConstrainGoogleAccount(): void
    {
        $this->expectException(ForeignKeyConstraintViolationException::class);

        $id = Uuid::randomHex();
        $merchantId = Uuid::randomHex();

        $this->createGoogleShoppingMerchantAccount($id, $merchantId);

        $merchantIdEdited = Uuid::randomHex();
        $accountIdEdited = Uuid::randomHex();

        $googleMerchantAccount = [
            [
                'id' => $id,
                'accountId' => $accountIdEdited,
                'merchantId' => $merchantIdEdited,
            ],
        ];

        $this->repository->update($googleMerchantAccount, $this->context);
    }

    public function testDeleteAccount(): void
    {
        $id = Uuid::randomHex();
        $merchantId = Uuid::randomHex();

        $this->createGoogleShoppingMerchantAccount($id, $merchantId);

        $this->repository->delete([['id' => $id]], $this->context);

        /** @var GoogleShoppingMerchantAccountEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->get($id);

        static::assertNull($entity);
    }

    private function createGoogleShoppingMerchantAccount(string $id, string $merchantId): array
    {
        $result = $this->createGoogleShoppingAccount($id);
        $accountId = $result['id'];

        $googleMerchantAccount = [
            [
                'id' => $id,
                'accountId' => $accountId,
                'merchantId' => $merchantId,
            ],
        ];

        $this->repository->create($googleMerchantAccount, $this->context);

        return [$id, $accountId, $merchantId];
    }
}
