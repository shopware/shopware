<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Client;

use Shopware\Core\Content\GoogleShopping\Client\Adapter\GoogleShoppingContentAccountResource;

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

    public function createShoppingContentAccountResource(): GoogleShoppingContentAccountResource
    {
        return new GoogleShoppingContentAccountResource($this->shoppingContentService->accounts, $this->googleShoppingClient);
    }
}
