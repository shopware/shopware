<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\AppService;
use Shopware\Core\Framework\App\Exception\AppValidationException;
use Shopware\Core\Framework\App\Exception\UserAbortedCommandException;
use Shopware\Core\Framework\App\Lifecycle\RefreshableAppDryRun;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\ManifestValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[AsCommand(name: 'app:refresh', description: 'Refreshes an app', aliases: ['app:update'])]
#[Package('core')]
class RefreshAppCommand extends Command
{
    public function __construct(
        private readonly AppService $appService,
        private readonly AppPrinter $appPrinter,
        private readonly ManifestValidator $manifestValidator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'The name of the app')
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
        $requestedApps = $input->getArgument('name');

        if (\count($requestedApps)) {
            $refreshableApps = $refreshableApps->filter($requestedApps);
        }

        if ($refreshableApps->isEmpty()) {
            $io->note('Nothing to install, update or delete.');

            return self::SUCCESS;
        }

        if (!$input->getOption('force')) {
            try {
                $this->grantPermissions($refreshableApps, $io);
            } catch (UserAbortedCommandException) {
                $io->error('Aborting due to user input.');

                return self::FAILURE;
            }
        }

        if (!$input->getOption('no-validate')) {
            $hasViolations = $this->validateRefreshableApps($refreshableApps, $io, $context);

            if ($hasViolations === 1) {
                return self::FAILURE;
            }
        }

        $fails = $this->appService->doRefreshApps($input->getOption('activate'), $context, $refreshableApps->getAppNames());

        $this->appPrinter->printInstalledApps($io, $context);
        $this->appPrinter->printIncompleteInstallations($io, $fails);

        return self::SUCCESS;
    }

    private function validateRefreshableApps(RefreshableAppDryRun $refreshableApps, ShopwareStyle $io, Context $context): int
    {
        $refreshableManifests = array_merge(
            $refreshableApps->getToBeInstalled(),
            $refreshableApps->getToBeUpdated()
        );

        // validate refreshable apps
        $invalids = [];
        foreach ($refreshableManifests as $refreshableManifest) {
            try {
                $this->manifestValidator->validate($refreshableManifest, $context);
            } catch (AppValidationException | XmlParsingException $e) {
                $invalids[] = $e->getMessage();
            }
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

            $this->appPrinter->checkHosts($app, $io);
        }

        foreach ($refreshableApps->getToBeUpdated() as $app) {
            $this->grantPermissionsForApp($app, $io, false);

            $this->appPrinter->checkHosts($app, $io);
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
