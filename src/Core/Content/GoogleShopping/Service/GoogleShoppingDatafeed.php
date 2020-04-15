<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Service;

use Shopware\Core\Content\GoogleShopping\Aggregate\GoogleShoppingMerchantAccount\GoogleShoppingMerchantAccountEntity;
use Shopware\Core\Content\GoogleShopping\Client\Adapter\GoogleShoppingContentDatafeedsResource;
use Shopware\Core\Content\GoogleShopping\Exception\SalesChannelIsNotLinkedToProductExport;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class GoogleShoppingDatafeed
{
    /**
     * @var EntityRepositoryInterface
     */
    private $googleMerchantAccountRepository;

    /**
     * @var GoogleShoppingContentDatafeedsResource
     */
    private $googleShoppingContentDatafeedsResource;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    public function __construct(
        EntityRepositoryInterface $googleMerchantAccountRepository,
        GoogleShoppingContentDatafeedsResource $googleShoppingContentDatafeedsResource,
        EntityRepositoryInterface $salesChannelRepository
    ) {
        $this->googleShoppingContentDatafeedsResource = $googleShoppingContentDatafeedsResource;
        $this->googleMerchantAccountRepository = $googleMerchantAccountRepository;
        $this->salesChannelRepository = $salesChannelRepository;
    }

    public function getStatus(GoogleShoppingMerchantAccountEntity $merchantAccount): array
    {
        return $this->googleShoppingContentDatafeedsResource->getStatus($merchantAccount->getMerchantId(), $merchantAccount->getDatafeedId());
    }

    public function get(GoogleShoppingMerchantAccountEntity $merchantAccount): array
    {
        return $this->googleShoppingContentDatafeedsResource->get($merchantAccount->getMerchantId(), $merchantAccount->getDatafeedId());
    }

    public function syncProduct(string $merchantId, string $datafeedId): array
    {
        return $this->googleShoppingContentDatafeedsResource->fetchNow($merchantId, $datafeedId);
    }

    public function write(GoogleShoppingMerchantAccountEntity $merchantAccount, SalesChannelEntity $salesChannel, Context $context): array
    {
        if ($merchantAccount->getDatafeedId()) {
            return $this->update($merchantAccount, $salesChannel, $context);
        }

        return $this->insert($merchantAccount, $salesChannel, $context);
    }

    private function update(GoogleShoppingMerchantAccountEntity $merchantAccount, SalesChannelEntity $salesChannel, Context $context): array
    {
        $productExport = $this->getProductExports($salesChannel->getId(), $context);

        $datafeedReq = $this->googleShoppingContentDatafeedsResource->createRequestDataFeed($salesChannel->getName(), $productExport);

        return $this->googleShoppingContentDatafeedsResource->update($merchantAccount->getMerchantId(), $merchantAccount->getDatafeedId(), $datafeedReq);
    }

    private function insert(GoogleShoppingMerchantAccountEntity $merchantAccount, SalesChannelEntity $salesChannel, Context $context): array
    {
        $productExport = $this->getProductExports($salesChannel->getId(), $context);

        $datafeedReq = $this->googleShoppingContentDatafeedsResource->createRequestDataFeed($salesChannel->getName(), $productExport);

        $datafeed = $this->googleShoppingContentDatafeedsResource->insert($merchantAccount->getMerchantId(), $datafeedReq);

        $this->googleMerchantAccountRepository->update(
            [
                [
                    'id' => $merchantAccount->getId(),
                    'datafeedId' => $datafeed['id'],
                ],
            ],
            $context
        );

        return $datafeed;
    }

    private function getProductExports(string $salesChannelId, Context $context): ProductExportEntity
    {
        $criteria = new Criteria([$salesChannelId]);
        $criteria->addAssociation('productExports.salesChannelDomain');
        $criteria->addAssociation('productExports.storefrontSalesChannel.country');
        $criteria->addAssociation('productExports.storefrontSalesChannel.language.locale');

        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $this->salesChannelRepository->search($criteria, $context)->get($salesChannelId);

        if (!$salesChannel->getProductExports()->first()) {
            throw new SalesChannelIsNotLinkedToProductExport();
        }

        return $salesChannel->getProductExports()->first();
    }
}
