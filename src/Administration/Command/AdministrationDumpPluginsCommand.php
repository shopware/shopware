<?php declare(strict_types=1);

namespace Shopware\Administration\Command;

use Exception;
use Shopware\Core\Kernel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AdministrationDumpPluginsCommand extends Command
{
    /**
     * @var Kernel
     */
    private $kernel;

    public function __construct(Kernel $kernel)
    {
        parent::__construct();
        $this->kernel = $kernel;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('administration:dump:plugins')
            ->setDescription('Creating json file with path config for administration modules from plugins.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->searchPluginDirectories();

        $style = new SymfonyStyle($input, $output);
        $style->success('Successfully dumped administration modules configuration');
    }

    protected function searchPluginDirectories(): void
    {
        $plugins = [];

        foreach ($this->kernel::getPlugins()->getActives() as $pluginName => $plugin) {
            // First try to load the main.js
            try {
                $indexFile = $this->kernel->locateResource('@' . $pluginName . '/Resources/views/administration/main.js');
            } catch (Exception $e) {
                $indexFile = null;
            }

            if ($indexFile === null) {
                // If we haven't found a javascript file, try to find a TypeScript file
                try {
                    $indexFile = $this->kernel->locateResource('@' . $pluginName . '/Resources/views/administration/main.ts');
                } catch (Exception $e) {
                    continue;
                }
            }

            $baseDirectory = $this->kernel->locateResource('@' . $pluginName);

            try {
                $customWebPackConfig = $this->kernel->locateResource('@' . $pluginName . '/Resources/views/administration/build/webpack.config.js');
            } catch (Exception $e) {
                $customWebPackConfig = false;
            }

            // return the path relative to the project dir
            $plugins[$pluginName] = [
                'base' => $baseDirectory,
                'entry' => $this->getPathRelativeToProjectDir($indexFile),
                'webpackConfig' => $customWebPackConfig === false ? $customWebPackConfig : $this->getPathRelativeToProjectDir($customWebPackConfig),
            ];
        }

        file_put_contents(
            $this->kernel->getCacheDir() . '/../../config_administration_plugins.json',
            json_encode($plugins)
        );
    }

    /**
     * return a path relative to the project dir
     */
    private function getPathRelativeToProjectDir(string $absolute): string
    {
        $projectDir = $this->kernel->getProjectDir();
        $relative = str_replace($projectDir, '', $absolute);
        $relative = ltrim($relative, '/');

        return $relative;
    }
}
