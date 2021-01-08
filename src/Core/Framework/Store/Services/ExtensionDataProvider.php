<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Store\Exception\ExtensionNotFoundException;
use Shopware\Core\Framework\Store\Search\ExtensionCriteria;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Store\Struct\ReviewCollection;
use Shopware\Core\Framework\Store\Struct\ReviewSummaryStruct;

/**
 * @internal
 */
class ExtensionDataProvider extends AbstractExtensionDataProvider
{
    public const HEADER_NAME_TOTAL_COUNT = 'SW-Meta-Total';

    /**
     * @var StoreClient
     */
    private $dataClient;

    /**
     * @var ExtensionLoader
     */
    private $extensionLoader;

    /**
     * @var EntityRepositoryInterface
     */
    private $appRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepository;

    public function __construct(
        StoreClient $client,
        ExtensionLoader $extensionLoader,
        EntityRepositoryInterface $appRepository,
        EntityRepositoryInterface $pluginRepository
    ) {
        $this->dataClient = $client;
        $this->extensionLoader = $extensionLoader;
        $this->appRepository = $appRepository;
        $this->pluginRepository = $pluginRepository;
    }

    public function getListing(ExtensionCriteria $criteria, Context $context): ExtensionCollection
    {
        $listingResponse = $this->dataClient->listExtensions($criteria, $context);
        $extensionListing = $this->extensionLoader->loadFromListingArray($context, $listingResponse['data']);

        $total = $listingResponse['headers'][self::HEADER_NAME_TOTAL_COUNT][0] ?? 0;
        $extensionListing->setTotal((int) $total);

        return $extensionListing;
    }

    public function getListingFilters(Context $context): array
    {
        return $this->dataClient->listListingFilters($context);
    }

    public function getExtensionDetails(int $id, Context $context): ExtensionStruct
    {
        $detailResponse = $this->dataClient->extensionDetail($id, $context);

        return $this->extensionLoader->loadFromArray($context, $detailResponse);
    }

    public function getReviews(int $extensionId, ExtensionCriteria $criteria, Context $context): array
    {
        $reviewsResponse = $this->dataClient->extensionDetailReviews($extensionId, $criteria, $context);

        return [
            'summary' => ReviewSummaryStruct::fromArray($reviewsResponse['summary']),
            'reviews' => new ReviewCollection($reviewsResponse['reviews']),
        ];
    }

    public function getInstalledExtensions(Context $context): ExtensionCollection
    {
        $criteria = new Criteria();
        $criteria->addAssociation('translations');

        /** @var AppCollection $installedApps */
        $installedApps = $this->appRepository->search($criteria, $context)->getEntities();

        /** @var PluginCollection $installedPlugins */
        $installedPlugins = $this->pluginRepository->search($criteria, $context)->getEntities();
        $pluginCollection = $this->extensionLoader->loadFromPluginCollection($context, $installedPlugins);

        return $this->extensionLoader->loadFromAppCollection($context, $installedApps)->merge($pluginCollection);
    }

    public function getAppEntityFromTechnicalName(string $technicalName, Context $context): AppEntity
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('name', $technicalName));
        $app = $this->appRepository->search($criteria, $context)->getEntities()->first();

        if ($app === null) {
            throw ExtensionNotFoundException::fromTechnicalName($technicalName);
        }

        return $app;
    }

    public function getAppEntityFromId(string $id, Context $context): AppEntity
    {
        $criteria = new Criteria([$id]);
        $app = $this->appRepository->search($criteria, $context)->getEntities()->first();

        if ($app === null) {
            throw ExtensionNotFoundException::fromId($id);
        }

        return $app;
    }

    protected function getDecorated(): AbstractExtensionDataProvider
    {
        throw new DecorationPatternException(self::class);
    }
}
