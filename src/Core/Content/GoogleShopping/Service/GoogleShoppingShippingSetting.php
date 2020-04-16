<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Service;

use Shopware\Core\Content\GoogleShopping\Client\Adapter\GoogleShoppingContentShippingSettingResource;
use Shopware\Core\Content\GoogleShopping\Exception\SalesChannelHasNoDefaultCountry;
use Shopware\Core\Content\GoogleShopping\Exception\SalesChannelHasNoDefaultShippingMethod;
use Shopware\Core\Content\GoogleShopping\Exception\SalesChannelIsNotLinkedToProductExport;
use Shopware\Core\Content\GoogleShopping\GoogleShoppingRequest;

class GoogleShoppingShippingSetting
{
    /**
     * @var GoogleShoppingContentShippingSettingResource
     */
    private $googleShoppingContentShippingSettingResource;

    public function __construct(
        GoogleShoppingContentShippingSettingResource $googleShoppingContentShippingSettingResource
    ) {
        $this->googleShoppingContentShippingSettingResource = $googleShoppingContentShippingSettingResource;
    }

    public function update(GoogleShoppingRequest $googleShoppingRequest, string $merchantId, float $rate)
    {
        $productExport = $googleShoppingRequest->getSalesChannel()->getProductExports()->first();
        if (!$productExport) {
            throw new SalesChannelIsNotLinkedToProductExport();
        }

        $shippingMethod = $productExport->getStorefrontSalesChannel()->getShippingMethod();

        if (!$shippingMethod) {
            throw new SalesChannelHasNoDefaultShippingMethod();
        }

        $country = $productExport->getStorefrontSalesChannel()->getCountry();
        if (!$country) {
            throw new SalesChannelHasNoDefaultCountry();
        }

        $deliveryTimeInDays = new DeliveryTimeInDays($shippingMethod->getDeliveryTime());

        return $this->googleShoppingContentShippingSettingResource->update(
            $shippingMethod,
            $deliveryTimeInDays,
            $country->getIso(),
            $productExport->getCurrency()->getIsoCode(),
            $merchantId,
            $merchantId,
            $rate
        );
    }
}
