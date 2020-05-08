<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Client;

use Shopware\Core\Content\GoogleShopping\Client\Adapter\UserProfileResource;

class GoogleShoppingUserProfileFactory
{
    /**
     * @var \Google_Service_Oauth2
     */
    private $userProfileService;

    /**
     * @var GoogleShoppingClient
     */
    private $googleShoppingClient;

    public function __construct(GoogleShoppingClient $googleShoppingClient)
    {
        $this->googleShoppingClient = $googleShoppingClient;
        $this->userProfileService = new \Google_Service_Oauth2($googleShoppingClient);
    }

    public function createUserProfileResource(): UserProfileResource
    {
        return new UserProfileResource($this->userProfileService->userinfo_v2_me);
    }
}
