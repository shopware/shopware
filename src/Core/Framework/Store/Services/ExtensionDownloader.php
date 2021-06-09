<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\Exception\ClientException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Store\Authentication\AuthenticationProvider;
use Shopware\Core\Framework\Store\Exception\CanNotDownloadPluginManagedByComposerException;
use Shopware\Core\Framework\Store\Exception\StoreApiException;
use Shopware\Core\Framework\Store\Exception\StoreTokenMissingException;
use Shopware\Core\Framework\Store\Struct\PluginDownloadDataStruct;

/**
 * @internal
 */
class ExtensionDownloader
{
    private const DEFAULT_LOCALE = 'en_GB';

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

    /**
     * @var ExtensionLoader
     */
    private $extensionLoader;

    public function __construct(
        EntityRepositoryInterface $pluginRepository,
        AuthenticationProvider $authenticationProvider,
        StoreClient $storeClient,
        PluginManagementService $pluginManagementService,
        ExtensionLoader $extensionLoader
    ) {
        $this->pluginRepository = $pluginRepository;
        $this->authenticationProvider = $authenticationProvider;
        $this->storeClient = $storeClient;
        $this->pluginManagementService = $pluginManagementService;
        $this->extensionLoader = $extensionLoader;
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

        $localeCode = $this->extensionLoader->getLocaleCodeFromLanguageId($context) ?? self::DEFAULT_LOCALE;

        try {
            $data = $this->storeClient->getDownloadDataForPlugin($technicalName, $storeToken, $localeCode, $storeToken !== '');
        } catch (ClientException $e) {
            throw new StoreApiException($e);
        }

        $this->pluginManagementService->downloadStorePlugin($data, $context);

        return $data;
    }
}
