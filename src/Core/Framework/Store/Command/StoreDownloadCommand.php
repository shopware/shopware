<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Command;

use GuzzleHttp\Exception\ClientException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Store\Exception\CanNotDownloadPluginManagedByComposerException;
use Shopware\Core\Framework\Store\Exception\StoreApiException;
use Shopware\Core\Framework\Store\Exception\StoreTokenMissingException;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StoreDownloadCommand extends Command
{
    public static $defaultName = 'store:download';

    /**
     * @var StoreClient
     */
    private $storeClient;

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepo;

    /**
     * @var PluginManagementService
     */
    private $pluginManagementService;

    /**
     * @var PluginLifecycleService
     */
    private $pluginLifecycleService;

    /**
     * @var EntityRepositoryInterface
     */
    private $userRepository;

    public function __construct(
        StoreClient $storeClient,
        EntityRepositoryInterface $pluginRepo,
        PluginManagementService $pluginManagementService,
        PluginLifecycleService $pluginLifecycleService,
        EntityRepositoryInterface $userRepository
    ) {
        $this->storeClient = $storeClient;
        $this->pluginRepo = $pluginRepo;
        $this->pluginManagementService = $pluginManagementService;
        $this->pluginLifecycleService = $pluginLifecycleService;
        $this->userRepository = $userRepository;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('pluginName', 'p', InputOption::VALUE_REQUIRED, 'Name of plugin')
            ->addOption('language', 'l', InputOption::VALUE_OPTIONAL, 'Language')
            ->addOption('user', 'u', InputOption::VALUE_OPTIONAL, 'User')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createDefaultContext();

        $pluginName = (string) $input->getOption('pluginName');
        $language = (string) $input->getOption('language');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('plugin.name', $pluginName));

        /** @var PluginEntity|null $plugin */
        $plugin = $this->pluginRepo->search($criteria, $context)->first();

        if ($plugin !== null && $plugin->getManagedByComposer()) {
            throw new CanNotDownloadPluginManagedByComposerException('can not downloads plugins managed by composer from store api');
        }

        $user = $input->getOption('user');
        $unauthenticated = !$user;
        if ($unauthenticated) {
            $storeToken = '';
        } else {
            $storeToken = $this->getUserStoreToken($context, $user);
        }

        try {
            $data = $this->storeClient->getDownloadDataForPlugin($pluginName, $storeToken, $language, !$unauthenticated);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        $this->pluginManagementService->downloadStorePlugin($data, $context);

        /** @var PluginEntity|null $plugin */
        $plugin = $this->pluginRepo->search($criteria, $context)->first();
        if ($plugin && $plugin->getUpgradeVersion()) {
            $this->pluginLifecycleService->updatePlugin($plugin, $context);
        }

        return self::SUCCESS;
    }

    private function getUserStoreToken(Context $context, string $user): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('user.username', $user));

        /** @var UserEntity|null $userEntity */
        $userEntity = $this->userRepository->search($criteria, $context)->first();

        if ($userEntity === null || $userEntity->getStoreToken() === null) {
            throw new StoreTokenMissingException();
        }

        return $userEntity->getStoreToken();
    }
}
