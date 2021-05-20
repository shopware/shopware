<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\Exception\AppAlreadyInstalledException;
use Shopware\Core\Framework\App\Exception\UserAbortedCommandException;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class InstallAppCommand extends Command
{
    protected static $defaultName = 'app:install';

    /**
     * @var string
     */
    private $appDir;

    /**
     * @var AppLifecycle
     */
    private $appLifecycle;

    /**
     * @var AppPrinter
     */
    private $appPrinter;

    /**
     * @var ValidateAppCommand
     */
    private $validateAppCommand;

    public function __construct(string $appDir, AppLifecycle $appLifecycle, AppPrinter $appPrinter, ValidateAppCommand $validateAppCommand)
    {
        parent::__construct();
        $this->appDir = $appDir;
        $this->appLifecycle = $appLifecycle;
        $this->appPrinter = $appPrinter;
        $this->validateAppCommand = $validateAppCommand;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $manifest = $this->getManifest($input, $io);

        if (!$manifest) {
            return self::FAILURE;
        }

        if (!$input->getOption('force')) {
            try {
                $this->checkPermissions($manifest, $io);
            } catch (UserAbortedCommandException $e) {
                $io->error('Aborting due to user input.');

                return self::FAILURE;
            }
        }

        if (!$input->getOption('no-validate')) {
            $invalids = $this->validateAppCommand->validate($manifest->getPath());

            if (\count($invalids) > 0) {
                // as only one app is validated - only one exception can occur
                $io->error($invalids[0]);

                return self::FAILURE;
            }

            $io->success('app is valid');
        }

        try {
            $this->appLifecycle->install($manifest, $input->getOption('activate'), Context::createDefaultContext());
        } catch (AppAlreadyInstalledException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }

        $io->success('App installed successfully.');

        return self::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setDescription('Installs the app in the folder with the given name')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'The name of the app, has also to be the name of the folder under
                which the app can be found under custom/apps'
            )->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force the installing of the app, it will automatically grant all requested permissions.'
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

    private function getManifest(InputInterface $input, ShopwareStyle $io): ?Manifest
    {
        $name = $input->getArgument('name');
        $manifestPath = sprintf('%s/%s/manifest.xml', $this->appDir, $name);
        if (!is_file($manifestPath)) {
            $io->error(
                sprintf(
                    'No app with name "%s" found.
                    Please make sure that a folder with that name exist in the custom/apps folder
                    and that it contains a manifest.xml file.',
                    $name
                )
            );

            return null;
        }

        return Manifest::createFromXmlFile($manifestPath);
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
