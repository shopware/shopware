<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Client\Adapter;

use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\GoogleShopping\Client\GoogleShoppingClient;
use Shopware\Core\Content\GoogleShopping\Service\DeliveryTimeInDays;

class GoogleShoppingContentShippingSettingResource
{
    /**
     * @var \Google_Service_ShoppingContent_Resource_Shippingsettings
     */
    private $shippingSettings;

    /**
     * @var GoogleShoppingClient
     */
    private $googleShoppingClient;

    public function __construct(
        \Google_Service_ShoppingContent_Resource_Shippingsettings $shippingSettings,
        GoogleShoppingClient $googleShoppingClient
    ) {
        $this->shippingSettings = $shippingSettings;
        $this->googleShoppingClient = $googleShoppingClient;
    }

    public function update(
        ShippingMethodEntity $shippingMethod,
        DeliveryTimeInDays $deliveryTimeInDays,
        string $country,
        string $currency,
        string $merchantId,
        string $accountId,
        float $rate
    ): array {
        $deliveryTime = $this->initDeliveryTime($deliveryTimeInDays->getMin(), $deliveryTimeInDays->getMax());

        $price = $this->initPrice($currency, $rate);

        $service = $this->createShippingService($deliveryTime, $price, $country, $currency, $shippingMethod->getName(), $shippingMethod->getActive());

        $settings = $this->initShippingSetting();
        $settings->setServices($service);

        return (array) $this->shippingSettings->update($merchantId, $accountId, $settings)->toSimpleObject();
    }

    private function initShippingSetting(): \Google_Service_ShoppingContent_ShippingSettings
    {
        $setting = new \Google_Service_ShoppingContent_ShippingSettings();
        $setting->setPostalCodeGroups([]);

        return $setting;
    }

    private function initDeliveryTime(int $min, int $max): \Google_Service_ShoppingContent_DeliveryTime
    {
        $deliveryTime = new \Google_Service_ShoppingContent_DeliveryTime();
        $deliveryTime->setMinTransitTimeInDays($min);
        $deliveryTime->setMaxTransitTimeInDays($max);

        return $deliveryTime;
    }

    private function initPrice(string $currency, float $rate): \Google_Service_ShoppingContent_Price
    {
        $price = new \Google_Service_ShoppingContent_Price();
        $price->setValue($rate);
        $price->setCurrency($currency);

        return $price;
    }

    private function createShippingService(
        \Google_Service_ShoppingContent_DeliveryTime $deliveryTime,
        \Google_Service_ShoppingContent_Price $price,
        string $country,
        string $currency,
        string $serviceName,
        bool $active
    ): \Google_Service_ShoppingContent_Service {
        $value = new \Google_Service_ShoppingContent_Value();
        $value->setFlatRate($price);

        $rateGroup = new \Google_Service_ShoppingContent_RateGroup();
        $rateGroup->setSingleValue($value);

        $service = new \Google_Service_ShoppingContent_Service();
        $service->setName($serviceName);
        $service->setActive($active);
        $service->setDeliveryCountry($country);
        $service->setCurrency($currency);
        $service->setDeliveryTime($deliveryTime);
        $service->setRateGroups([$rateGroup]);

        return $service;
    }
}
