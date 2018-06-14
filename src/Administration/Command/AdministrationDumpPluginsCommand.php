<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Administration\Command;

use Shopware\Core\Kernel;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

class AdministrationDumpPluginsCommand extends ContainerAwareCommand
{
    /**
     * @var Kernel
     */
    private $kernel;

    public function __construct(KernelInterface $kernel)
    {
        parent::__construct();
        $this->kernel = $kernel;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
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

    protected function searchPluginDirectories()
    {
        $plugins = [];

        foreach ($this->kernel::getPlugins()->getActivePlugins() as $pluginName => $plugin) {
            // First try to load the main.js
            try {
                $indexFile = $this->kernel->locateResource('@' . $pluginName . '/Resources/views/administration/main.js');
            } catch (\Exception $e) {
                $indexFile = null;
            }

            if (empty($indexFile)) {
                // If we haven't found a javascript file, try to find a TypeScript file
                try {
                    $indexFile = $this->kernel->locateResource('@' . $pluginName . '/Resources/views/administration/main.ts');
                } catch(\Exception $e) {
                    continue;
                }
            }

            $baseDirectory =  $this->kernel->locateResource('@' . $pluginName);

            try {
                $customWebPackConfig = $this->kernel->locateResource('@' . $pluginName . '/Resources/views/administration/build/webpack.config.js');
            } catch (\Exception $e) {
                $customWebPackConfig = false;
            }

            // return the path relative to the projectdir
            $plugins[$pluginName] = [
                'base' => $baseDirectory,
                'entry' => $this->getPathRelativeToProjectDir($indexFile),
                'webpackConfig' => $customWebPackConfig === false ? $customWebPackConfig : $this->getPathRelativeToProjectDir($customWebPackConfig)
            ];
        }

        file_put_contents(
            $this->kernel->getCacheDir() . '/../../config_administration_plugins.json',
            json_encode($plugins)
        );
    }

    /**
     * return a path relative to the projectdir
     *
     * @param string $absolute
     *
     * @return string
     */
    private function getPathRelativeToProjectDir(string $absolute): string
    {
        $projectDir = $this->kernel->getProjectDir();
        $relative = str_replace($projectDir, '', $absolute);
        $relative = ltrim($relative, '/');

        return $relative;
    }
}
