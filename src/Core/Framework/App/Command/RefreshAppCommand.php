<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\AppService;
use Shopware\Core\Framework\App\Exception\UserAbortedCommandException;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopware\Core\Framework\App\Lifecycle\RefreshableAppDryRun;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\ManifestValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
#[AsCommand(name: 'app:refresh', description: 'Refreshes an app', aliases: ['app:update'])]
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
            )
            /** @deprecated tag:v6.8.0 - the pre-flight report will always run */
            ->addOption(
                'no-validate',
                null,
                InputOption::VALUE_NONE,
                '[DEPRECATED] Skip the pre-flight validation report. Blocking findings still refuse an app during the refresh itself.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $context = Context::createCLIContext();

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

        if ($input->getOption('no-validate')) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'The "--no-validate" option of the "app:refresh" command is deprecated and will be removed in v6.8.0.'
            );
        }

        if (!$input->getOption('no-validate')) {
            $hasViolations = $this->validateRefreshableApps($refreshableApps, $io, $context);

            if ($hasViolations === 1) {
                return self::FAILURE;
            }
        }

        $fails = $this->appService->doRefreshApps(
            new AppInstallParameters(activate: $input->getOption('activate'), acceptPermissions: true),
            $context,
            $refreshableApps->getAppNames()
        );

        $this->appPrinter->printInstalledApps($io, $context);
        $this->appPrinter->printIncompleteInstallations($io, $fails);

        return self::SUCCESS;
    }

    private function validateRefreshableApps(RefreshableAppDryRun $refreshableApps, SymfonyStyle $io, Context $context): int
    {
        $refreshableManifests = array_merge(
            $refreshableApps->getToBeInstalled(),
            $refreshableApps->getToBeUpdated()
        );

        // validate refreshable apps
        $invalids = [];
        foreach ($refreshableManifests as $refreshableManifest) {
            $result = $this->manifestValidator->validate($refreshableManifest, $context);

            if (!$result->isOk()) {
                $invalids[] = AppException::validationFailed(
                    $refreshableManifest->getMetadata()->getName(),
                    $result->errors
                )->getMessage();
            }
        }

        if ($invalids !== []) {
            foreach ($invalids as $invalid) {
                $io->error($invalid);
            }

            return self::FAILURE;
        }

        $io->success('all refreshable apps are valid');

        return self::SUCCESS;
    }

    private function grantPermissions(RefreshableAppDryRun $refreshableApps, SymfonyStyle $io): void
    {
        $default = true;
        if ($refreshableApps->getToBeDeleted() !== []) {
            $default = false;
        }

        if (!$io->confirm(
            \sprintf(
                "%d apps will be installed, %d apps will be updated and %d apps will be deleted.\nDo you want to continue?",
                \count($refreshableApps->getToBeInstalled()),
                \count($refreshableApps->getToBeUpdated()),
                \count($refreshableApps->getToBeDeleted())
            ),
            $default
        )) {
            throw AppException::userAborted();
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

    private function grantPermissionsForApp(Manifest $app, SymfonyStyle $io, bool $install = true): void
    {
        if ($app->getPermissions()) {
            $this->appPrinter->printPermissions($app, $io, $install);

            if (!$io->confirm(
                \sprintf('Do you want to grant these permissions for app "%s"?', $app->getMetadata()->getName()),
                false
            )) {
                throw AppException::userAborted();
            }
        }
    }
}
