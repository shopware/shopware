<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Client\Adapter;

class GoogleShoppingContentProductResource
{
    const ITEM_CHANNEL = 'online';
    const SHOPPING_DESTINATION = 'Shopping';

    /**
     * @var \Google_Service_ShoppingContent_Resource_Products
     */
    private $productResource;

    /**
     * @var \Google_Service_ShoppingContent_Resource_Productstatuses
     */
    private $productStatusResource;

    public function __construct(
        \Google_Service_ShoppingContent_Resource_Products $productResource,
        \Google_Service_ShoppingContent_Resource_Productstatuses $productStatusResource
    ) {
        $this->productResource = $productResource;
        $this->productStatusResource = $productStatusResource;
    }

    public function getProductStatuses(string $merchantId, \stdClass $datafeedTarget, array $productNumbers): array
    {
        $productStatusEntries = [];

        $restIdPrefix = sprintf(
            '%s:%s:%s:',
            self::ITEM_CHANNEL,
            $datafeedTarget->language,
            $datafeedTarget->country
        );

        foreach ($productNumbers as $index => $productNumber) {
            $productStatusEntry = new \Google_Service_ShoppingContent_ProductstatusesCustomBatchRequestEntry();
            $productStatusEntry->setIncludeAttributes(true);
            $productStatusEntry->setMerchantId($merchantId);
            $productStatusEntry->setProductId($restIdPrefix . $productNumber);
            $productStatusEntry->setMethod('GET');
            $productStatusEntry->setDestinations([self::SHOPPING_DESTINATION]);
            $productStatusEntry->setBatchId($index);

            $productStatusEntries[] = $productStatusEntry;
        }

        $productStatusRequest = new \Google_Service_ShoppingContent_ProductstatusesCustomBatchRequest();
        $productStatusRequest->setEntries($productStatusEntries);

        return (array) $this->productStatusResource->custombatch($productStatusRequest)->getEntries();
    }
}
