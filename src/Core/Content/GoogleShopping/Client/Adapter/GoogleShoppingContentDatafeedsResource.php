<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Client\Adapter;

use Shopware\Core\Content\GoogleShopping\Client\GoogleShoppingClient;
use Shopware\Core\Content\ProductExport\ProductExportEntity;

class GoogleShoppingContentDatafeedsResource
{
    private const CONTENT_TYPE = 'products';

    private const INCLUDED_DESTINATIONS = ['Shopping'];

    /**
     * @var \Google_Service_ShoppingContent_Resource_Datafeeds
     */
    private $resource;

    /**
     * @var \Google_Service_ShoppingContent_Resource_Datafeedstatuses
     */
    private $resDatafeedStatuses;

    /**
     * @var GoogleShoppingClient
     */
    private $googleShoppingClient;

    public function __construct(\Google_Service_ShoppingContent_Resource_Datafeeds $resource, \Google_Service_ShoppingContent_Resource_Datafeedstatuses $resDatafeedStatuses, GoogleShoppingClient $googleShoppingClient)
    {
        $this->resource = $resource;
        $this->googleShoppingClient = $googleShoppingClient;
        $this->resDatafeedStatuses = $resDatafeedStatuses;
    }

    public function fetchNow(string $merchantId, string $datafeedId): array
    {
        return (array) $this->resource->fetchnow($merchantId, $datafeedId);
    }

    public function insert(string $merchantId, \Google_Service_ShoppingContent_Datafeed $datafeed): array
    {
        return (array) $this->resource->insert($merchantId, $datafeed);
    }

    public function update(string $merchantId, string $datafeedId, \Google_Service_ShoppingContent_Datafeed $datafeed): array
    {
        $datafeed->setId($datafeedId);

        return (array) $this->resource->update($merchantId, $datafeedId, $datafeed)->toSimpleObject();
    }

    public function getStatus(string $merchantId, string $datafeedId): array
    {
        return (array) $this->resDatafeedStatuses->get($merchantId, $datafeedId)->toSimpleObject();
    }

    public function get(string $merchantId, string $datafeedId): array
    {
        return (array) $this->resource->get($merchantId, $datafeedId)->toSimpleObject();
    }

    public function list(string $merchantId): array
    {
        return (array) $this->resource->listDatafeeds($merchantId)->toSimpleObject();
    }

    public function createRequestDataFeed(string $name, ProductExportEntity $productExport): \Google_Service_ShoppingContent_Datafeed
    {
        $datafeed = new \Google_Service_ShoppingContent_Datafeed();

        $datafeed->setName($name);
        $datafeed->setContentType($this::CONTENT_TYPE);
        $datafeed->setFileName($productExport->getFileName());

        $datafeedTarget = new \Google_Service_ShoppingContent_DatafeedTarget();

        $codeLang = $productExport->getStorefrontSalesChannel()->getLanguage()->getLocale()->getCode();
        $codeLang639_1 = explode('-', $codeLang)[0];

        $datafeedTarget->setLanguage($codeLang639_1);
        $datafeedTarget->setCountry($productExport->getStorefrontSalesChannel()->getCountry()->getIso());
        $datafeedTarget->setIncludedDestinations($this::INCLUDED_DESTINATIONS);
        $datafeed->setTargets([$datafeedTarget]);

        $fetch_schedule
            = new \Google_Service_ShoppingContent_DatafeedFetchSchedule();

        $fetch_schedule->setHour(gmdate('H', $productExport->getInterval()));
        $fetch_schedule->setTimeZone(ini_get('date.timezone'));
        $fetch_schedule->setPaused($productExport->isPausedSchedule());

        $fileUrl = $productExport->getSalesChannelDomain()->getUrl() . '/export/' . $productExport->getAccessKey() . '/' . $productExport->getFileName();
        $fetch_schedule->setFetchUrl($fileUrl);

        $datafeed->setFetchSchedule($fetch_schedule);

        return $datafeed;
    }
}
