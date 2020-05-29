<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Client;

use Shopware\Core\Content\GoogleShopping\Client\Adapter\GoogleShoppingContentAccountResource;
use Shopware\Core\Content\GoogleShopping\Client\Adapter\GoogleShoppingContentDatafeedsResource;
use Shopware\Core\Content\GoogleShopping\Client\Adapter\GoogleShoppingContentProductResource;
use Shopware\Core\Content\GoogleShopping\Client\Adapter\GoogleShoppingContentShippingSettingResource;

class GoogleShoppingContentFactory
{
    /**
     * @var \Google_Service_ShoppingContent
     */
    private $shoppingContentService;

    /**
     * @var GoogleShoppingClient
     */
    private $googleShoppingClient;

    public function __construct(GoogleShoppingClient $googleShoppingClient)
    {
        $this->googleShoppingClient = $googleShoppingClient;

        $this->shoppingContentService = new \Google_Service_ShoppingContent($googleShoppingClient);
    }

    public function createContentAccountResource(): GoogleShoppingContentAccountResource
    {
        return new GoogleShoppingContentAccountResource(
            $this->shoppingContentService->accounts,
            $this->shoppingContentService->accountstatuses,
            $this->googleShoppingClient
        );
    }

    public function createShoppingContentShippingSettingResource(): GoogleShoppingContentShippingSettingResource
    {
        return new GoogleShoppingContentShippingSettingResource($this->shoppingContentService->shippingsettings, $this->googleShoppingClient);
    }

    public function createContentProductResource(): GoogleShoppingContentProductResource
    {
        return new GoogleShoppingContentProductResource(
            $this->shoppingContentService->products,
            $this->shoppingContentService->productstatuses
        );
    }

    public function createShoppingContentDatafeedsResource(): GoogleShoppingContentDatafeedsResource
    {
        return new GoogleShoppingContentDatafeedsResource($this->shoppingContentService->datafeeds, $this->shoppingContentService->datafeedstatuses, $this->googleShoppingClient);
    }
}
