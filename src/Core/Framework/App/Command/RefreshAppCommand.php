<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\AppService;
use Shopware\Core\Framework\App\Exception\UserAbortedCommandException;
use Shopware\Core\Framework\App\Lifecycle\RefreshableAppDryRun;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RefreshAppCommand extends Command
{
    protected static $defaultName = 'app:refresh';

    /**
     * @var AppService
     */
    private $appService;

    /**
     * @var AppPrinter
     */
    private $appPrinter;

    public function __construct(AppService $appService, AppPrinter $appPrinter)
    {
        parent::__construct();

        $this->appService = $appService;
        $this->appPrinter = $appPrinter;
    }

    protected function configure(): void
    {
        $this->setDescription('Refreshes the installed Apps')
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force the refreshing of apps, apps will automatically be granted all requested permissions.'
            )->addOption(
                'activate',
                'a',
                InputOption::VALUE_NONE,
                'Activate the app after installing it'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $context = Context::createDefaultContext();

        $refreshableApps = $this->appService->getRefreshableAppInfo($context);
        if ($refreshableApps->isEmpty()) {
            $io->note('Nothing to install, update or delete.');

            return 0;
        }

        if (!$input->getOption('force')) {
            try {
                $this->grantPermissions($refreshableApps, $io);
            } catch (UserAbortedCommandException $e) {
                $io->error('Aborting due to user input.');

                return 1;
            }
        }

        $fails = $this->appService->refreshApps((bool) $input->getOption('activate'), $context);

        $this->appPrinter->printInstalledApps($io, $context);
        $this->appPrinter->printIncompleteInstallations($io, $fails);

        return 0;
    }

    private function grantPermissions(RefreshableAppDryRun $refreshableApps, ShopwareStyle $io): void
    {
        if (!$io->confirm(
            sprintf(
                '%d apps will be installed, %d apps will be updated and
                    %d apps will be deleted. Do you want to continue?',
                count($refreshableApps->getToBeInstalled()),
                count($refreshableApps->getToBeUpdated()),
                count($refreshableApps->getToBeDeleted())
            )
        )) {
            throw new UserAbortedCommandException();
        }

        foreach ($refreshableApps->getToBeInstalled() as $app) {
            $this->grantPermissionsForApp($app, $io);
        }

        foreach ($refreshableApps->getToBeUpdated() as $app) {
            $this->grantPermissionsForApp($app, $io, false);
        }
    }

    private function grantPermissionsForApp(Manifest $app, ShopwareStyle $io, bool $install = true): void
    {
        if ($app->getPermissions()) {
            $this->appPrinter->printPermissions($app, $io, $install);

            if (!$io->confirm(
                sprintf('Do you want to grant these permissions for app "%s"?', $app->getMetadata()->getName()),
                false
            )) {
                throw new UserAbortedCommandException();
            }
        }
    }
}
