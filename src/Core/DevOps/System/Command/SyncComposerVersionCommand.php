<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\System\Command;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[AsCommand(
    name: 'sync:composer:version',
    description: 'Syncs the composer version with the shopware version',
)]
#[Package('framework')]
class SyncComposerVersionCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly string $projectDir,
        private readonly Filesystem $fileSystem,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Fail when files gets changed, but don\'t change them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Checking sync of composer dependencies');

        $rootComposerJson = json_decode($this->fileSystem->readFile($this->projectDir . '/composer.json'), true, 512, \JSON_THROW_ON_ERROR);

        $bundleJsons = glob($this->projectDir . '/src/*/composer.json', \GLOB_NOSORT);
        \assert(\is_array($bundleJsons));

        $isDryMode = $input->getOption('dry-run');
        if ($isDryMode) {
            $io->warning('Running in dry-run mode: no files will be changed.');
        }

        $changed = [];
        $isInRootButNotInBundle = [];
        $isInBundleButNotInRoot = [];

        foreach ($bundleJsons as $bundleJsonPath) {
            $bundleJson = json_decode($this->fileSystem->readFile($bundleJsonPath), true, 512, \JSON_THROW_ON_ERROR);
            $bundleName = basename(\dirname($bundleJsonPath));

            foreach (['require', 'require-dev'] as $field) {
                // Check if all root dependencies are in the bundle composer.json files
                foreach ($rootComposerJson[$field] ?? [] as $package => $version) {
                    if (isset($bundleJson[$field][$package])) {
                        if ($bundleJson[$field][$package] !== $version) {
                            $bundleJson[$field][$package] = $version;
                            $changed[$bundleName] = true;
                        }
                    } elseif ($field === 'require') {
                        // Dev dependencies should not be synced from root to bundles
                        $isInRootButNotInBundle[$package][] = $bundleName;
                    }
                }

                // Check if all bundle dependencies are in the root composer.json file
                foreach ($bundleJson[$field] ?? [] as $package => $version) {
                    if ($package === 'shopware/core') {
                        continue;
                    }
                    if (!isset($rootComposerJson[$field][$package])) {
                        $isInBundleButNotInRoot[$package][] = $bundleName;
                    }
                }
            }

            if (!($changed[$bundleName] ?? false)) {
                continue;
            }

            if ($isDryMode) {
                continue;
            }

            $io->info('Updating composer.json of "' . $bundleName . '" bundle.');
            $this->fileSystem->dumpFile($bundleJsonPath, json_encode($bundleJson, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) . \PHP_EOL);
        }

        if ($isInBundleButNotInRoot !== []) {
            $message = 'The following packages are defined in the bundles but not in the root composer.json:';
            foreach ($isInBundleButNotInRoot as $package => $bundles) {
                $message .= "\n- \"$package\" from bundles: " . implode(', ', $bundles);
            }
            $io->error($message);

            return self::FAILURE;
        }

        if ($isInRootButNotInBundle !== []) {
            $message = '';
            foreach ($isInRootButNotInBundle as $package => $bundles) {
                // If the bundles count for a package is lower than the overall bundles count,
                // it means that the package is used, so we do not to consider it.
                // If the package is not used in any bundle, the count will be the same as the overall bundles count.
                if (\count($bundles) < \count($bundleJsons)) {
                    continue;
                }
                $message .= "\n- $package";
            }
            if ($message !== '') {
                $message = 'The following packages are defined in the root composer.json but not in the bundles:' . $message;
                $io->error($message);

                return self::FAILURE;
            }
        }

        if ($changed !== []) {
            if ($isDryMode) {
                $io->error("Composer dependencies of bundles are not in sync with the root composer.json file.\nPlease run the `sync:composer:version` command without the --dry-run option to sync them.");

                return self::FAILURE;
            }

            $io->info('Composer dependencies of bundles synced with the root composer.json file.');
        } else {
            $io->success('Composer dependencies of bundles are already in sync with the root composer.json file.');
        }

        return self::SUCCESS;
    }
}
