<?php declare(strict_types=1);

namespace Shopware\Recovery\Update\Command;

use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Recovery\Common\IOHelper;
use Shopware\Recovery\Common\Steps\ErrorResult;
use Shopware\Recovery\Common\Steps\MigrationStep;
use Shopware\Recovery\Common\Steps\ValidResult;
use Shopware\Recovery\Update\Cleanup;
use Shopware\Recovery\Update\CleanupFilesFinder;
use Shopware\Recovery\Update\DependencyInjection\Container;
use Shopware\Recovery\Update\FilesystemFactory;
use Shopware\Recovery\Update\PathBuilder;
use Shopware\Recovery\Update\Steps\UnpackStep;
use Shopware\Recovery\Update\Utils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends Command
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @var IOHelper
     */
    private $IOHelper;

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('update');
        $this->setDescription('Updates shopware');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->container = $this->getApplication()->getContainer();
        $this->container->setParameter('update.config', []);

        $this->IOHelper = $ioService = new IOHelper(
            $input,
            $output,
            $this->getHelper('question')
        );

        if (!is_dir(UPDATE_ASSET_PATH)) {
            $ioService->writeln('No update files found.');

            return self::FAILURE;
        }

        $version = $this->container->get('shopware.version');

        if ($ioService->isInteractive()) {
            $ioService->cls();
            $ioService->printBanner();
            $ioService->writeln('<info>Welcome to the Shopware updater </info>');
            $ioService->writeln(sprintf('Shopware Version %s', $version));
            $ioService->writeln('');
            $ioService->ask('Press return to start the update.');
            $ioService->cls();
        }

        $this->unpackFiles();
        $this->migrateDatabase(MigrationStep::UPDATE);
        $this->migrateDatabase(MigrationStep::UPDATE_DESTRUCTIVE);
        $this->cleanup();
        $this->regenerateCertificate();
        $this->writeLockFile();

        $ioService->cls();
        $ioService->writeln('');
        $ioService->writeln('');
        $ioService->writeln('<info>The update has been finished successfully.</info>');
        $ioService->writeln('Your shop is currently in maintenance mode.');
        $ioService->writeln(sprintf('Please delete <question>%s</question> to finish the update.', UPDATE_ASSET_PATH));
        $ioService->writeln('');

        return self::SUCCESS;
    }

    private function unpackFiles(): void
    {
        $this->IOHelper->writeln('Replace system files...');
        if (!UPDATE_FILES_PATH || !is_dir(UPDATE_FILES_PATH)) {
            $this->IOHelper->writeln('skipped...');

            return;
        }

        /** @var FilesystemFactory $factory */
        $factory = $this->container->get('filesystem.factory');
        $localFilesytem = $factory->createLocalFilesystem();
        $remoteFilesystem = $factory->createLocalFilesystem();

        /** @var PathBuilder $pathBuilder */
        $pathBuilder = $this->container->get('path.builder');

        $debug = false;
        $step = new UnpackStep($localFilesytem, $remoteFilesystem, $pathBuilder, $debug);

        $offset = 0;
        $total = 0;
        do {
            $result = $step->run($offset, $total);
            if ($result instanceof ErrorResult) {
                throw new \Exception($result->getMessage(), 0, $result->getException());
            }
            $offset = $result->getOffset();
            $total = $result->getTotal();
        } while ($result instanceof ValidResult);
    }

    private function migrateDatabase(string $modus): void
    {
        /** @var MigrationCollectionLoader $migrationCollectionLoader */
        $migrationCollectionLoader = $this->container->get('migration.collection.loader');

        $versionSelectionMode = $modus === MigrationStep::UPDATE_DESTRUCTIVE
            // only execute safe destructive migrations
            ? MigrationCollectionLoader::VERSION_SELECTION_SAFE
            : MigrationCollectionLoader::VERSION_SELECTION_ALL;

        $coreCollection = $migrationCollectionLoader->collectAllForVersion(
            (string) $this->container->get('shopware.version'),
            $versionSelectionMode
        );

        if ($modus === MigrationStep::UPDATE) {
            $versions = $coreCollection->getExecutableMigrations();
            $this->IOHelper->writeln('Apply database migrations...');
        } else {
            $versions = $coreCollection->getExecutableDestructiveMigrations();
            $this->IOHelper->writeln('Apply database destructive migrations...');
        }

        $progress = $this->IOHelper->createProgressBar(\count($versions));
        $progress->start();

        $step = new MigrationStep($coreCollection);
        $offset = 0;
        do {
            $progress->setProgress($offset);
            $result = $step->run($modus, $offset, 1);
            if ($result instanceof ErrorResult) {
                throw new \Exception($result->getMessage(), 0, $result->getException());
            }

            $offset = $result->getOffset();
            $progress->setProgress($offset);
        } while ($result instanceof ValidResult);

        $progress->finish();
        $this->IOHelper->writeln('');
    }

    private function cleanup(): void
    {
        $this->IOHelper->writeln('Cleanup old files, clearing caches...');

        $this->cleanupFiles();
    }

    private function cleanupFiles(): void
    {
        /** @var CleanupFilesFinder $cleanupFilesFinder */
        $cleanupFilesFinder = $this->container->get('cleanup.files.finder');
        foreach ($cleanupFilesFinder->getCleanupFiles() as $path) {
            Utils::cleanPath($path);
        }

        /** @var Cleanup $cleanup */
        $cleanup = $this->container->get('shopware.update.cleanup');
        $cleanup->cleanup(false);
    }

    private function regenerateCertificate(): void
    {
        $lastGeneratedVersionFile = SW_PATH . '/config/jwt/version';
        $lastGeneratedVersion = null;

        if (is_readable($lastGeneratedVersionFile)) {
            $lastGeneratedVersion = file_get_contents($lastGeneratedVersionFile);
        }

        $requiredVersion = '6.0.0 ea1.1';
        if (!$lastGeneratedVersion || version_compare($lastGeneratedVersion, $requiredVersion) === -1) {
            $jwtCertificateService = $this->container->get('jwt_certificate.writer');
            $jwtCertificateService->generate();
            file_put_contents($lastGeneratedVersionFile, $this->container->get('shopware.version'));
        }
    }

    private function writeLockFile(): void
    {
        if (is_dir(SW_PATH . '/recovery/install')) {
            /** @var \Shopware\Recovery\Common\SystemLocker $systemLocker */
            $systemLocker = $this->container->get('system.locker');
            $systemLocker();
        }
    }
}
