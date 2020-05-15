<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping\Aggregate\GoogleShoppingAdsAccount;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\GoogleShopping\Aggregate\GoogleShoppingAdsAccount\GoogleShoppingAdsAccountEntity;
use Shopware\Core\Content\GoogleShopping\Aggregate\GoogleShoppingMerchantAccount\GoogleShoppingMerchantAccountEntity;
use Shopware\Core\Content\Test\GoogleShopping\GoogleShoppingIntegration;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use function Flag\skipTestNext6050;

class GoogleShoppingAdsAccountEntityTest extends TestCase
{
    use IntegrationTestBehaviour;
    use GoogleShoppingIntegration;

    /** @var EntityRepository */
    private $merchantRepository;

    /** @var EntityRepository */
    private $adsRepository;

    /** @var Context */
    private $context;

    protected function setUp(): void
    {
        skipTestNext6050($this);

        $this->merchantRepository = $this->getContainer()->get('google_shopping_merchant_account.repository');
        $this->adsRepository = $this->getContainer()->get('google_shopping_ads_account.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testCreateAccount(): void
    {
        $id = Uuid::randomHex();
        $adsId = 'ADS_123';
        $adsManagerId = 'ADS_MANAGER_123';

        list($id, $merchantId) = $this->createGoogleShoppingAdsAccount($id, $adsId, $adsManagerId);

        /** @var GoogleShoppingAdsAccountEntity $entity */
        $entity = $this->adsRepository->search(new Criteria([$id]), $this->context)->get($id);

        static::assertEquals($merchantId, $entity->getMerchantAccountId());
        static::assertEquals($adsId, $entity->getAdsId());
        static::assertEquals($adsManagerId, $entity->getAdsManagerId());
    }

    public function testUpdateAccount(): void
    {
        $id = Uuid::randomHex();
        $adsId = 'ADS_123';
        $adsManagerId = 'ADS_MANAGER_123';

        list($id, $merchantId) = $this->createGoogleShoppingAdsAccount($id, $adsId, $adsManagerId);

        $adsIdEdited = 'ADS_123_EDITED';
        $adsManagerIdEdited = 'ADS_MANAGER_123_EDITED';

        $googleMerchantAccount = [
            [
                'id' => $id,
                'adsId' => $adsIdEdited,
                'adsManagerId' => $adsManagerIdEdited,
            ],
        ];

        $this->adsRepository->update($googleMerchantAccount, $this->context);

        /** @var GoogleShoppingAdsAccountEntity $entity */
        $entity = $this->adsRepository->search(new Criteria([$id]), $this->context)->get($id);

        static::assertEquals($merchantId, $entity->getMerchantAccountId());
        static::assertEquals($adsIdEdited, $entity->getAdsId());
        static::assertEquals($adsManagerIdEdited, $entity->getAdsManagerId());
        static::assertEquals($id, $entity->getId());
    }

    public function testUpdateAccountForeignKeyConstrainGoogleMerchantAccount(): void
    {
        $this->expectException(ForeignKeyConstraintViolationException::class);

        $id = Uuid::randomHex();
        $adsId = 'ADS_123';
        $adsManagerId = 'ADS_MANAGER_123';

        list($id) = $this->createGoogleShoppingAdsAccount($id, $adsId, $adsManagerId);

        $adsIdEdited = 'ADS_123_EDITED';
        $adsManagerIdEdited = 'ADS_MANAGER_123_EDITED';

        $googleMerchantAccount = [
            [
                'id' => $id,
                'merchantAccountId' => Uuid::randomHex(),
                'adsId' => $adsIdEdited,
                'adsManagerId' => $adsManagerIdEdited,
            ],
        ];

        $this->adsRepository->update($googleMerchantAccount, $this->context);
    }

    public function testDeleteAccount(): void
    {
        $id = Uuid::randomHex();
        $adsId = 'ADS_123';
        $adsManagerId = 'ADS_MANAGER_123';

        list($id) = $this->createGoogleShoppingAdsAccount($id, $adsId, $adsManagerId);

        $this->adsRepository->delete([['id' => $id]], $this->context);

        /** @var GoogleShoppingMerchantAccountEntity $entity */
        $entity = $this->adsRepository->search(new Criteria([$id]), $this->context)->get($id);

        static::assertNull($entity);
    }

    private function createGoogleShoppingMerchantAccount(string $id, string $merchantId): string
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

        $this->merchantRepository->create($googleMerchantAccount, $this->context);

        return $id;
    }

    private function createGoogleShoppingAdsAccount(string $id, string $adsId, string $adsManagerId): array
    {
        $merchantId = Uuid::randomHex();
        $merchantDbId = $this->createGoogleShoppingMerchantAccount($id, $merchantId);

        $googleAdsAccount = [
            [
                'id' => $id,
                'adsId' => $adsId,
                'adsManagerId' => $adsManagerId,
                'merchantAccountId' => $merchantDbId,
            ],
        ];

        $this->adsRepository->create($googleAdsAccount, $this->context);

        return [$id, $merchantDbId];
    }
}
