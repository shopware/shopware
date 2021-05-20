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

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
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

    /**
     * @var ValidateAppCommand
     */
    private $validateAppCommand;

    public function __construct(AppService $appService, AppPrinter $appPrinter, ValidateAppCommand $validateAppCommand)
    {
        parent::__construct();

        $this->appService = $appService;
        $this->appPrinter = $appPrinter;
        $this->validateAppCommand = $validateAppCommand;
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
            )->addOption(
                'no-validate',
                null,
                InputOption::VALUE_NONE,
                'Skip app validation.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $context = Context::createDefaultContext();

        $refreshableApps = $this->appService->getRefreshableAppInfo($context);
        if ($refreshableApps->isEmpty()) {
            $io->note('Nothing to install, update or delete.');

            return self::SUCCESS;
        }

        if (!$input->getOption('force')) {
            try {
                $this->grantPermissions($refreshableApps, $io);
            } catch (UserAbortedCommandException $e) {
                $io->error('Aborting due to user input.');

                return self::FAILURE;
            }
        }

        if (!$input->getOption('no-validate')) {
            $hasViolations = $this->validateRefreshableApps($refreshableApps, $io);

            if ($hasViolations === 1) {
                return self::FAILURE;
            }
        }

        $fails = $this->appService->doRefreshApps($input->getOption('activate'), $context);

        $this->appPrinter->printInstalledApps($io, $context);
        $this->appPrinter->printIncompleteInstallations($io, $fails);

        return self::SUCCESS;
    }

    private function validateRefreshableApps(RefreshableAppDryRun $refreshableApps, ShopwareStyle $io): int
    {
        $refreshableManifests = array_merge(
            $refreshableApps->getToBeInstalled(),
            $refreshableApps->getToBeUpdated()
        );

        // validate refreshable apps
        $invalids = [];
        foreach ($refreshableManifests as $refreshableManifest) {
            $validation = $this->validateAppCommand->validate($refreshableManifest->getPath());

            if (!$validation) {
                continue;
            }

            $invalids[] = $validation;
        }

        if (\count($invalids) > 0) {
            foreach ($invalids as $invalid) {
                $io->error($invalid);
            }

            return self::FAILURE;
        }

        $io->success('all refreshable apps are valid');

        return self::SUCCESS;
    }

    private function grantPermissions(RefreshableAppDryRun $refreshableApps, ShopwareStyle $io): void
    {
        if (!$io->confirm(
            sprintf(
                "%d apps will be installed, %d apps will be updated and %d apps will be deleted.\nDo you want to continue?",
                \count($refreshableApps->getToBeInstalled()),
                \count($refreshableApps->getToBeUpdated()),
                \count($refreshableApps->getToBeDeleted())
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
