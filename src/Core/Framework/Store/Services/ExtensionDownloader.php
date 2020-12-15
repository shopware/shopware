<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Store\Authentication\AuthenticationProvider;
use Shopware\Core\Framework\Store\Exception\CanNotDownloadPluginManagedByComposerException;
use Shopware\Core\Framework\Store\Exception\StoreNotAvailableException;
use Shopware\Core\Framework\Store\Exception\StoreTokenMissingException;
use Shopware\Core\Framework\Store\Struct\PluginDownloadDataStruct;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ExtensionDownloader
{
    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepository;

    /**
     * @var AuthenticationProvider
     */
    private $authenticationProvider;

    /**
     * @var StoreClient
     */
    private $storeClient;

    /**
     * @var PluginManagementService
     */
    private $pluginManagementService;

    public function __construct(
        EntityRepositoryInterface $pluginRepository,
        AuthenticationProvider $authenticationProvider,
        StoreClient $storeClient,
        PluginManagementService $pluginManagementService
    ) {
        $this->pluginRepository = $pluginRepository;
        $this->authenticationProvider = $authenticationProvider;
        $this->storeClient = $storeClient;
        $this->pluginManagementService = $pluginManagementService;
    }

    public function download(string $technicalName, Context $context): PluginDownloadDataStruct
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('plugin.name', $technicalName));

        /** @var PluginEntity|null $plugin */
        $plugin = $this->pluginRepository->search($criteria, $context)->first();

        if ($plugin !== null && $plugin->getManagedByComposer()) {
            throw new CanNotDownloadPluginManagedByComposerException('can not downloads plugins managed by composer from store api');
        }

        try {
            $storeToken = $this->authenticationProvider->getUserStoreToken($context);
        } catch (StoreTokenMissingException $e) {
            $storeToken = '';
        }

        $data = $this->storeClient->getDownloadDataForPlugin($technicalName, $storeToken, 'de-DE', $storeToken !== '');

        $statusCode = $this->pluginManagementService->downloadStorePlugin($data->getLocation(), $context);
        if ($statusCode !== Response::HTTP_OK) {
            throw new StoreNotAvailableException();
        }

        return $data;
    }
}
