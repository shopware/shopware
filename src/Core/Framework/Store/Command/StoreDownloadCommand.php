<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Command;

use GuzzleHttp\Exception\ClientException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Store\StoreException;
use Shopware\Core\System\User\UserCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(
    name: 'store:download',
    description: 'Downloads a plugin from the store',
)]
#[Package('checkout')]
class StoreDownloadCommand extends Command
{
    /**
     * @param EntityRepository<PluginCollection> $pluginRepo
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(
        private readonly StoreClient $storeClient,
        private readonly EntityRepository $pluginRepo,
        private readonly PluginManagementService $pluginManagementService,
        private readonly PluginLifecycleService $pluginLifecycleService,
        private readonly EntityRepository $userRepository,
    ) {
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
        $context = Context::createCLIContext();

        $pluginName = (string) $input->getOption('pluginName');
        $user = $input->getOption('user');

        $context = $this->getUserContextFromInput($user, $context);

        $this->validatePluginIsNotManagedByComposer($pluginName, $context);

        try {
            $data = $this->storeClient->getDownloadDataForPlugin($pluginName, $context);
        } catch (ClientException $exception) {
            throw StoreException::storeError($exception);
        }

        $this->pluginManagementService->downloadStorePlugin($data, $context);

        $plugin = $this->getPluginFromInput($pluginName, $context);

        if ($plugin === null) {
            // don't update plugins that are not installed
            return self::SUCCESS;
        }

        if ($plugin->getUpgradeVersion()) {
            $this->pluginLifecycleService->updatePlugin($plugin, $context);
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

        $userEntity = $this->userRepository->search($criteria, $context)->getEntities()->first();
        if ($userEntity === null) {
            return $context;
        }

        return Context::createCLIContext(new AdminApiSource($userEntity->getId()));
    }

    private function validatePluginIsNotManagedByComposer(string $pluginName, Context $context): void
    {
        $plugin = $this->getPluginFromInput($pluginName, $context);

        if ($plugin === null) {
            return;
        }

        if ($plugin->getManagedByComposer() && !$plugin->isLocatedInCustomPluginDirectory()) {
            throw StoreException::cannotDeleteManaged($pluginName);
        }
    }

    private function getPluginFromInput(string $pluginName, Context $context): ?PluginEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('plugin.name', $pluginName));

        return $this->pluginRepo->search($criteria, $context)->getEntities()->first();
    }
}
