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

namespace Shopware\Storefront\Theme;

use Assetic\Asset\AssetInterface;
use Shopware\Framework\Plugin\Plugin;
use Shopware\Kernel;

class LessphpFilter extends \Assetic\Filter\LessphpFilter
{
    /**
     * @var Kernel
     */
    private $kernel;

    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    public function filterLoad(AssetInterface $asset): void
    {
        $this->dumpPluginLess();
        $this->dumpThemeConfiguration();

        if (!$this->kernel->isDebug()) {
            $this->setFormatter('compressed');
        }

        parent::filterLoad($asset);
    }

    private function dumpPluginLess(): void
    {
        $output = '// ' . date('Y-m-d H:i:s') . PHP_EOL;
        $allPath = '/Resources/public/less/all.less';
        $pluginImportTemplate = '@import (optional) "@{%s}%s";';

        /** @var Plugin $plugin */
        foreach ($this->kernel::getPlugins()->getActivePlugins() as $plugin) {
            $output .= sprintf($pluginImportTemplate, $plugin->getName(), $allPath) . PHP_EOL;
        }

        file_put_contents(
            $this->kernel->getCacheDir() . '/plugins.less',
            $output
        );
    }

    private function dumpThemeConfiguration(): void
    {
    }
}
