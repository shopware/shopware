<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\GoogleShopping\Aggregate\GoogleShoppingMerchantAccount\GoogleShoppingMerchantAccountEntity;
use Shopware\Core\Content\GoogleShopping\Client\Adapter\GoogleShoppingContentDatafeedsResource;
use Shopware\Core\Content\GoogleShopping\Service\GoogleShoppingDatafeed;
use Shopware\Core\Content\Test\GoogleShopping\GoogleShoppingIntegration;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use function Flag\skipTestNext6050;

class GoogleShoppingDatafeedTest extends TestCase
{
    use IntegrationTestBehaviour;
    use GoogleShoppingIntegration;

    /** @var EntityRepositoryInterface */
    private $repositoryProductExport;

    /**
     * @var EntityRepository
     */
    private $repository;

    /**
     * @var EntityRepository
     */
    private $googleMerchantAccountRepository;

    /**
     * @var MockObject
     */
    private $googleShoppingContentDatafeedResource;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var GoogleShoppingDatafeed
     */
    private $googleShoppingDatafeed;

    /**
     * @var EntityRepositoryInterface|null
     */
    private $salesChannelRepository;

    protected function setUp(): void
    {
        skipTestNext6050($this);
        $this->repositoryProductExport = $this->getContainer()->get('product_export.repository');
        $this->repository = $this->getContainer()->get('google_shopping_merchant_account.repository');
        $this->googleMerchantAccountRepository = $this->getContainer()->get('google_shopping_merchant_account.repository');
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');

        $this->context = Context::createDefaultContext();
        $this->googleShoppingContentDatafeedResource = $this->createMock(GoogleShoppingContentDatafeedsResource::class);
        $this->googleShoppingDatafeed = new GoogleShoppingDatafeed($this->repository, $this->googleShoppingContentDatafeedResource, $this->salesChannelRepository);
    }

    public function testGetDatafeed(): void
    {
        $merchantId = Uuid::randomHex();
        $datafeedId = '109171885';

        $this->googleShoppingContentDatafeedResource->expects(static::once())->method('get')->willReturn([
            'contentType' => 'products',
            'fileName' => '1bdcd3ae03b74ab28d67c2c8571ab060',
            'id' => $datafeedId,
            'kind' => 'content#datafeed',
            'name' => 'G-shop',
        ]);

        $merchantAccount = new GoogleShoppingMerchantAccountEntity();
        $merchantAccount->setDatafeedId($datafeedId);
        $merchantAccount->setMerchantId($merchantId);

        $datafeed = $this->googleShoppingDatafeed->get($merchantAccount);

        static::assertEquals($datafeedId, $datafeed['id']);
    }

    public function testGetDatafeedStatus(): void
    {
        $merchantId = Uuid::randomHex();
        $datafeedId = '109171885';

        $this->googleShoppingContentDatafeedResource->expects(static::once())->method('getStatus')->willReturn([
            'country' => 'DE',
            'datafeedId' => $datafeedId,
            'itemsTotal' => '42',
            'itemsValid' => '0',
            'kind' => 'content#datafeedStatus',
            'language' => 'en',
            'lastUploadDate' => ' 2020-04-17T04:01:37Z',
            'processingStatus' => 'success',
        ]);

        $merchantAccount = new GoogleShoppingMerchantAccountEntity();
        $merchantAccount->setDatafeedId($datafeedId);
        $merchantAccount->setMerchantId($merchantId);

        $datafeed = $this->googleShoppingDatafeed->getStatus($merchantAccount);

        static::assertEquals('success', $datafeed['processingStatus']);
    }

    public function testWriteDataFeedNotExist(): void
    {
        $salesChannelId = $this->createSalesChannelGoogleShopping();

        $merchantId = Uuid::randomHex();
        $datafeedId = '109171885';
        $fileName = 'Testexport.csv';

        $this->googleShoppingContentDatafeedResource->expects(static::once())->method('insert')->willReturn([
            'contentType' => 'products',
            'fileName' => $fileName,
            'id' => $datafeedId,
            'kind' => 'content#datafeed',
            'name' => 'G-shop',
        ]);

        $googleAccount = $this->createGoogleShoppingAccount(Uuid::randomHex());

        $account = [
            'id' => Uuid::randomHex(),
            'merchantId' => $merchantId,
            'accountId' => $googleAccount['id'],
        ];

        $this->googleMerchantAccountRepository->create([$account], $this->context);

        $merchantAccount = $this->getMerchantAccountEntity($googleAccount);

        $datafeed = $this->googleShoppingDatafeed->write($merchantAccount, $this->getSalesChannel($salesChannelId), $this->context);

        static::assertEquals($datafeedId, $datafeed['id']);
        static::assertEquals($fileName, $datafeed['fileName']);

        $merchantAccount = $this->getMerchantAccountEntity($googleAccount);

        static::assertEquals($merchantId, $merchantAccount->getMerchantId());
        static::assertEquals($datafeedId, $merchantAccount->getDatafeedId());
    }

    public function testWriteDataFeedExist(): void
    {
        $salesChannelId = $this->createSalesChannelGoogleShopping();

        $merchantId = Uuid::randomHex();
        $datafeedId = '109171885';
        $fileName = 'Testexport.csv';

        $this->googleShoppingContentDatafeedResource->expects(static::once())->method('update')->willReturn([
            'contentType' => 'products',
            'fileName' => $fileName,
            'id' => $datafeedId,
            'kind' => 'content#datafeed',
            'name' => 'G-shop',
        ]);

        $googleAccount = $this->createGoogleShoppingAccount(Uuid::randomHex());

        $account = [
            'id' => Uuid::randomHex(),
            'merchantId' => $merchantId,
            'datafeedId' => $datafeedId,
            'accountId' => $googleAccount['id'],
        ];

        $this->googleMerchantAccountRepository->create([$account], $this->context);

        $merchantAccount = $this->getMerchantAccountEntity($googleAccount);

        $datafeed = $this->googleShoppingDatafeed->write($merchantAccount, $this->getSalesChannel($salesChannelId), $this->context);

        static::assertEquals($datafeedId, $datafeed['id']);
        static::assertEquals($fileName, $datafeed['fileName']);
    }

    private function getSalesChannel(string $salesChannelId): SalesChannelEntity
    {
        $criteria = new Criteria([$salesChannelId]);
        $criteria->addAssociation('googleShoppingAccount.googleShoppingMerchantAccount');
        $criteria->addAssociation('productExports.currency');
        $criteria->addAssociation('productExports.salesChannelDomain');
        $criteria->addAssociation('productExports.storefrontSalesChannel.shippingMethod');
        $criteria->addAssociation('productExports.storefrontSalesChannel.country');
        $criteria->addAssociation('productExports.storefrontSalesChannel.language');
        $criteria->addAssociation('productExports.storefrontSalesChannel.language.locale');

        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('sales_channel.repository');

        return $repository->search($criteria, $this->context)->first();
    }

    private function getMerchantAccountEntity(array $googleAccount): GoogleShoppingMerchantAccountEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('accountId', $googleAccount['id']));

        $merchantAccount = $this->googleMerchantAccountRepository->search($criteria, $this->context)->first();

        return $merchantAccount;
    }
}
