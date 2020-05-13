<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Service;

use Shopware\Core\Content\GoogleShopping\Client\Adapter\GoogleShoppingContentShippingSettingResource;
use Shopware\Core\Content\GoogleShopping\Exception\SalesChannelHasNoDefaultCountry;
use Shopware\Core\Content\GoogleShopping\Exception\SalesChannelHasNoDefaultShippingMethod;
use Shopware\Core\Content\GoogleShopping\Exception\SalesChannelIsNotLinkedToProductExport;
use Shopware\Core\Content\GoogleShopping\GoogleShoppingRequest;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class GoogleShoppingShippingSetting
{
    /**
     * @var GoogleShoppingContentShippingSettingResource
     */
    private $googleShoppingContentShippingSettingResource;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    public function __construct(
        GoogleShoppingContentShippingSettingResource $googleShoppingContentShippingSettingResource,
        EntityRepositoryInterface $salesChannelRepository
    ) {
        $this->googleShoppingContentShippingSettingResource = $googleShoppingContentShippingSettingResource;
        $this->salesChannelRepository = $salesChannelRepository;
    }

    public function update(GoogleShoppingRequest $googleShoppingRequest, string $merchantId, float $rate)
    {
        $storeFrontSaleChannel = $this->getStorefrontSalesChannel($googleShoppingRequest->getSalesChannel()->getId(), $googleShoppingRequest->getContext());

        $shippingMethod = $storeFrontSaleChannel->getShippingMethod();

        if (!$shippingMethod) {
            throw new SalesChannelHasNoDefaultShippingMethod();
        }

        $country = $storeFrontSaleChannel->getCountry();
        if (!$country) {
            throw new SalesChannelHasNoDefaultCountry();
        }

        $deliveryTimeInDays = new DeliveryTimeInDays($shippingMethod->getDeliveryTime());

        return $this->googleShoppingContentShippingSettingResource->update(
            $shippingMethod,
            $deliveryTimeInDays,
            $country->getIso(),
            $storeFrontSaleChannel->getCurrency()->getIsoCode(),
            $merchantId,
            $merchantId,
            $rate
        );
    }

    public function getStorefrontSalesChannel(string $salesChannelId, Context $context): SalesChannelEntity
    {
        $criteria = new Criteria([$salesChannelId]);
        $criteria->addAssociation('productExports.storefrontSalesChannel.currency');
        $criteria->addAssociation('productExports.storefrontSalesChannel.shippingMethod');
        $criteria->addAssociation('productExports.storefrontSalesChannel.country');

        $productExport = $this->getProductExportsByCriteria($criteria, $salesChannelId, $context);

        return $productExport->getStorefrontSalesChannel();
    }

    private function getProductExportsByCriteria(Criteria $criteria, string $salesChannelId, Context $context): ProductExportEntity
    {
        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $this->salesChannelRepository->search($criteria, $context)->get($salesChannelId);

        if (!$salesChannel->getProductExports()->first()) {
            throw new SalesChannelIsNotLinkedToProductExport();
        }

        return $salesChannel->getProductExports()->first();
    }
}
