<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\Exception\AppAlreadyInstalledException;
use Shopware\Core\Framework\App\Exception\AppValidationException;
use Shopware\Core\Framework\App\Exception\UserAbortedCommandException;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
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
#[AsCommand(
    name: 'app:install',
    description: 'Installs an app',
)]
#[Package('core')]
class InstallAppCommand extends Command
{
    public function __construct(
        private readonly AbstractAppLoader $appLoader,
        private readonly AbstractAppLifecycle $appLifecycle,
        private readonly AppPrinter $appPrinter,
        private readonly ManifestValidator $manifestValidator
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createDefaultContext();
        $io = new ShopwareStyle($input, $output);

        /** @var string|array<string> $names */
        $names = $input->getArgument('name');

        if (\is_string($names)) {
            $names = [$names];
        }

        $manifests = $this->getMatchingManifests($names);
        $success = self::SUCCESS;

        if (\count($manifests) === 0) {
            $io->info('Could not find any app with this name');

            return self::SUCCESS;
        }

        foreach ($manifests as $name => $manifest) {
            if (!$input->getOption('force')) {
                try {
                    $this->checkPermissions($manifest, $io);

                    $this->appPrinter->checkHosts($manifest, $io);
                } catch (UserAbortedCommandException $e) {
                    $io->error('Aborting due to user input.');

                    return self::FAILURE;
                }
            }

            if (!$input->getOption('no-validate')) {
                try {
                    $this->manifestValidator->validate($manifest, $context);
                } catch (AppValidationException | XmlParsingException $e) {
                    $io->error(sprintf('App installation of %s failed due: %s', $name, $e->getMessage()));

                    $success = self::FAILURE;

                    continue;
                }
            }

            try {
                $this->appLifecycle->install($manifest, $input->getOption('activate'), $context);
            } catch (AppAlreadyInstalledException) {
                $io->info(sprintf('App %s is already installed', $name));

                continue;
            }

            $io->success(sprintf('App %s has been successfully installed.', $name));
        }

        return (int) $success;
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'The name of the app')
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force the installing of the app, it will automatically grant all requested permissions.'
            )
            ->addOption(
                'activate',
                'a',
                InputOption::VALUE_NONE,
                'Activate the app after installing it'
            )
            ->addOption(
                'no-validate',
                null,
                InputOption::VALUE_NONE,
                'Skip app validation.'
            );
    }

    private function getMatchingManifests(array $requestedApps): array
    {
        $apps = $this->appLoader->load();
        $manifests = [];

        foreach ($requestedApps as $requestedApp) {
            foreach ($apps as $app => $manifest) {
                if (str_contains($app, (string) $requestedApp)) {
                    $manifests[$app] = $manifest;
                }
            }
        }

        return $manifests;
    }

    private function checkPermissions(Manifest $manifest, ShopwareStyle $io): void
    {
        if ($manifest->getPermissions()) {
            $this->appPrinter->printPermissions($manifest, $io, true);

            if (!$io->confirm(
                sprintf('Do you want to grant these permissions for app "%s"?', $manifest->getMetadata()->getName()),
                false
            )) {
                throw new UserAbortedCommandException();
            }
        }
    }
}
