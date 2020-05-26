<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Service;

use Shopware\Core\Content\GoogleShopping\Aggregate\GoogleShoppingMerchantAccount\GoogleShoppingMerchantAccountEntity;
use Shopware\Core\Content\GoogleShopping\Client\Adapter\GoogleShoppingContentDatafeedsResource;
use Shopware\Core\Content\GoogleShopping\Client\Adapter\GoogleShoppingContentProductResource;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class GoogleShoppingMerchantProduct
{
    const UNKNOWN_GOOGLE_STATUS = 'unknown';

    /**
     * @var GoogleShoppingContentProductResource
     */
    private $contentProductResource;

    /**
     * @var GoogleShoppingContentDatafeedsResource
     */
    private $contentDatafeedsResource;

    public function __construct(
        GoogleShoppingContentProductResource $contentProductResource,
        GoogleShoppingContentDatafeedsResource $contentDatafeedsResource
    ) {
        $this->contentProductResource = $contentProductResource;
        $this->contentDatafeedsResource = $contentDatafeedsResource;
    }

    public function listWithGoogleStatus(
        GoogleShoppingMerchantAccountEntity $merchantAccountEntity,
        SalesChannelEntity $storeFrontSalesChannel,
        array $productNumbers
    ) {
        $merchantId = $merchantAccountEntity->getMerchantId();

        $dataFeed = $this->contentDatafeedsResource->get($merchantId, $merchantAccountEntity->getDatafeedId());

        $dataFeedTarget = $this->findShopTarget($dataFeed['targets'], $storeFrontSalesChannel) ?? $dataFeed['targets'][0];

        $productGoogleStatuses = $dataFeedTarget ? $this->contentProductResource->getProductStatuses($merchantId, $dataFeedTarget, $productNumbers) : [];

        return $this->mapProductNumberWithGoogleStatus($productNumbers, $productGoogleStatuses);
    }

    private function findShopTarget(array $targets, SalesChannelEntity $storeFrontSalesChannel)
    {
        $codeLang = $storeFrontSalesChannel->getLanguage()->getLocale()->getCode();
        $codeLang639_1 = explode('-', $codeLang)[0];
        $countryIsoCode = $storeFrontSalesChannel->getCountry()->getIso();

        $matchTargets = array_filter($targets, function ($target) use ($codeLang639_1, $countryIsoCode) {
            return strcasecmp($target->country, $countryIsoCode) === 0 && strcasecmp($target->language, $codeLang639_1) === 0;
        });

        return reset($matchTargets) ?: null;
    }

    private function mapProductNumberWithGoogleStatus(array $productNumbers, array $productGoogleStatuses): array
    {
        /** @var \Google_Service_ShoppingContent_ProductstatusesCustomBatchResponseEntry $productStatus */
        foreach ($productGoogleStatuses as $productStatus) {
            $productGoogleStatus = $productStatus->getProductStatus();
            $batchId = $productStatus->getBatchId();

            if (!array_key_exists($batchId, $productNumbers)) {
                continue;
            }

            $originalProductNumber = $productNumbers[$batchId];

            if ($productGoogleStatus instanceof \Google_Service_ShoppingContent_ProductStatus) {
                $status = $this->getShoppingDestinationStatus($productGoogleStatus);
                $productGoogleStatus = (array) $productGoogleStatus->toSimpleObject();
                $productGoogleStatus['status'] = $status;
                $productNumbers[$originalProductNumber] = $productGoogleStatus;
            }

            unset($productNumbers[$batchId]);
        }

        return $productNumbers;
    }

    private function getShoppingDestinationStatus(\Google_Service_ShoppingContent_ProductStatus $productStatus): string
    {
        $destinationStatuses = $productStatus->getDestinationStatuses();

        if (empty($destinationStatuses)) {
            return self::UNKNOWN_GOOGLE_STATUS;
        }

        $shoppingDestinations = array_filter((array) $destinationStatuses, function (\Google_Service_ShoppingContent_ProductStatusDestinationStatus $destination) {
            return $destination->getDestination() === GoogleShoppingContentProductResource::SHOPPING_DESTINATION;
        });

        /** @var \Google_Service_ShoppingContent_ProductStatusDestinationStatus $shoppingDestination */
        $shoppingDestination = current($shoppingDestinations);

        return empty($shoppingDestination) ? self::UNKNOWN_GOOGLE_STATUS : $shoppingDestination->getStatus();
    }
}
