<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\System\Command;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use const PHP_EOL;

#[AsCommand(
    name: 'sync:composer:version',
    description: 'Syncs the composer version with the shopware version',
)]
#[Package('core')]
class SyncComposerVersionCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(private readonly string $projectDir)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rootComposerJson = json_decode((string) file_get_contents($this->projectDir . '/composer.json'), true, 512, \JSON_THROW_ON_ERROR);

        $bundleJsons = glob($this->projectDir . '/src/*/composer.json', \GLOB_NOSORT);
        \assert(\is_array($bundleJsons));

        foreach ($bundleJsons as $bundleJsonPath) {
            $bundleJson = json_decode((string) file_get_contents($bundleJsonPath), true, 512, \JSON_THROW_ON_ERROR);

            foreach (['require', 'require-dev'] as $field) {
                foreach ($rootComposerJson[$field] as $package => $version) {
                    if (isset($bundleJson[$field][$package])) {
                        $bundleJson[$field][$package] = $version;
                    }
                }
            }
            file_put_contents($bundleJsonPath, json_encode($bundleJson, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) . PHP_EOL);
        }

        return self::SUCCESS;
    }
}
