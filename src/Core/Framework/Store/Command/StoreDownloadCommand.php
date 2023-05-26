<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Command;

use GuzzleHttp\Exception\ClientException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Store\Exception\CanNotDownloadPluginManagedByComposerException;
use Shopware\Core\Framework\Store\Exception\StoreApiException;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Store\StoreException;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[AsCommand(
    name: 'store:download',
    description: 'Downloads a plugin from the store',
)]
#[Package('merchant-services')]
class StoreDownloadCommand extends Command
{
    private readonly string $relativePluginDir;

    public function __construct(
        private readonly StoreClient $storeClient,
        private readonly EntityRepository $pluginRepo,
        private readonly PluginManagementService $pluginManagementService,
        private readonly PluginLifecycleService $pluginLifecycleService,
        private readonly EntityRepository $userRepository,
        string $pluginDir,
        string $projectDir,
    ) {
        parent::__construct();

        $this->relativePluginDir = (new Filesystem())->makePathRelative($pluginDir, $projectDir);
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
        $user = $input->getOption('user');

        $context = $this->getUserContextFromInput($user, $context);

        $this->validatePluginIsNotManagedByComposer($pluginName, $context);

        try {
            $data = $this->storeClient->getDownloadDataForPlugin($pluginName, $context);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        $this->pluginManagementService->downloadStorePlugin($data, $context);

        try {
            $plugin = $this->getPluginFromInput($pluginName, $context);

            if ($plugin->getUpgradeVersion()) {
                $this->pluginLifecycleService->updatePlugin($plugin, $context);
            }
        } catch (PluginNotFoundException) {
            // don't update plugins that are not installed
        }

        return self::SUCCESS;
    }

    private function getUserContextFromInput(?string $userName, Context $context): Context
    {
        if (!$userName) {
            return $context;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('user.username', $userName));

        /** @var UserEntity|null $userEntity */
        $userEntity = $this->userRepository->search($criteria, $context)->first();

        if ($userEntity === null) {
            return $context;
        }

        return Context::createDefaultContext(new AdminApiSource($userEntity->getId()));
    }

    private function validatePluginIsNotManagedByComposer(string $pluginName, Context $context): void
    {
        try {
            $plugin = $this->getPluginFromInput($pluginName, $context);
        } catch (PluginNotFoundException) {
            // plugins no installed can still be downloaded
            return;
        }

        if ($plugin->getManagedByComposer() && !str_starts_with($plugin->getPath() ?? '', $this->relativePluginDir)) {
            if (Feature::isActive('v6.6.0.0')) {
                throw StoreException::cannotDeleteManaged($pluginName);
            }

            throw new CanNotDownloadPluginManagedByComposerException('can not download plugins managed by composer from store api');
        }
    }

    private function getPluginFromInput(string $pluginName, Context $context): PluginEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('plugin.name', $pluginName));

        /** @var PluginEntity|null $plugin */
        $plugin = $this->pluginRepo->search($criteria, $context)->first();

        if ($plugin === null) {
            throw new PluginNotFoundException($pluginName);
        }

        return $plugin;
    }
}
