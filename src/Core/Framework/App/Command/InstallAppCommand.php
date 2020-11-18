<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\Exception\UserAbortedCommandException;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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

    public function __construct(string $appDir, AppLifecycle $appLifecycle, AppPrinter $appPrinter)
    {
        parent::__construct();
        $this->appDir = $appDir;
        $this->appLifecycle = $appLifecycle;
        $this->appPrinter = $appPrinter;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $manifest = $this->getManifest($input, $io);

        if (!$manifest) {
            return 1;
        }

        if (!$input->getOption('force')) {
            try {
                $this->checkPermissions($manifest, $io);
            } catch (UserAbortedCommandException $e) {
                $io->error('Aborting due to user input.');

                return 1;
            }
        }

        $this->appLifecycle->install($manifest, (bool) $input->getOption('activate'), Context::createDefaultContext());

        $io->success('App installed successfully.');

        return 0;
    }

    protected function configure(): void
    {
        $this->setDescription('Installs the app in the folder with the given name')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'The name of the app, has also to be the name of the folder under
                which the app can be found under custom/apps'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force the install of the app, it will automatically grant all requested permissions.'
            )
            ->addOption(
                'activate',
                'a',
                InputOption::VALUE_NONE,
                'Activate the app after installing it'
            );
    }

    private function getManifest(InputInterface $input, ShopwareStyle $io): ?Manifest
    {
        /** @var string $name */
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
