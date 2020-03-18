<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Client\Adapter;

class UserProfileResource
{
    /**
     * @var \Google_Service_Oauth2_Resource_UserinfoV2Me
     */
    private $resource;

    public function __construct(\Google_Service_Oauth2_Resource_UserinfoV2Me $resource)
    {
        $this->resource = $resource;
    }

    public function get(array $opts = []): array
    {
        return (array) $this->resource->get($opts)->toSimpleObject();
    }
}
