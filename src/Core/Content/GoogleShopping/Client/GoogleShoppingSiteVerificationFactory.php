<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Client;

use Shopware\Core\Content\GoogleShopping\Client\Adapter\SiteVerificationResource;

class GoogleShoppingSiteVerificationFactory
{
    /**
     * @var \Google_Service_SiteVerification
     */
    private $siteVericationService;

    /**
     * @var GoogleShoppingClient
     */
    private $googleShoppingClient;

    public function __construct(GoogleShoppingClient $googleShoppingClient)
    {
        $this->googleShoppingClient = $googleShoppingClient;
        $this->siteVericationService = new \Google_Service_SiteVerification($googleShoppingClient);
    }

    public function createSiteVerificationResource(): SiteVerificationResource
    {
        return new SiteVerificationResource($this->siteVericationService->webResource);
    }
}
