<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Client\Adapter;

use Shopware\Core\Content\GoogleShopping\Exception\GoogleShoppingServiceException;

class SiteVerificationResource
{
    /**
     * @var \Google_Service_SiteVerification_Resource_WebResource
     */
    private $resource;

    public function __construct(\Google_Service_SiteVerification_Resource_WebResource $resource)
    {
        $this->resource = $resource;
    }

    public function get(string $siteUrl): array
    {
        return (array) $this->resource->get($siteUrl)->toSimpleObject();
    }

    public function insert($siteUrl, string $verificationMethod = 'ANALYTICS', bool $silentInsert = false): array
    {
        try {
            $site = new \Google_Service_SiteVerification_SiteVerificationWebResourceResourceSite();
            $site->setType('SITE');
            $site->setIdentifier($siteUrl);

            $postBody = new \Google_Service_SiteVerification_SiteVerificationWebResourceResource();
            $postBody->setSite($site);

            return (array) $this->resource->insert($verificationMethod, $postBody)->toSimpleObject();
        } catch (GoogleShoppingServiceException $exception) {
            if ($silentInsert) {
                return iterator_to_array($exception->getErrors());
            }

            throw $exception;
        }
    }
}
