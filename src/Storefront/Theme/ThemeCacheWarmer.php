<?php
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
use Assetic\AssetManager;
use Assetic\Util\VarUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class ThemeCacheWarmer implements CacheWarmerInterface
{
    /**
     * @var AssetManager
     */
    private $am;

    /**
     * @var string
     */
    private $basePath;
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container, AssetManager $am, string $basePath)
    {
        $this->container = $container;
        $this->am = $am;
        $this->basePath = $basePath;
    }

    public function warmUp($cacheDir): void
    {
        foreach ($this->am->getNames() as $name) {
            $this->dumpAsset($name);
        }
    }

    /**
     * Checks whether this warmer is optional or not.
     *
     * Optional warmers can be ignored on certain conditions.
     *
     * A warmer should return true if the cache can be
     * generated incrementally and on-demand.
     *
     * @return bool true if the warmer is optional, false otherwise
     */
    public function isOptional(): bool
    {
        return true;
    }

    /**
     * Writes an asset.
     *
     * If the application or asset is in debug mode, each leaf asset will be
     * dumped as well.
     *
     * @param string $name An asset name
     */
    private function dumpAsset($name)
    {
        $asset = $this->am->get($name);

        // dump each leaf if no combine
        foreach ($asset as $leaf) {
            $this->doDump($leaf);
        }
    }

    private function doDump(AssetInterface $asset)
    {
        $combinations = VarUtils::getCombinations(
            $asset->getVars(),
            $this->container->getParameter('assetic.variables')
        );

        foreach ($combinations as $combination) {
            $asset->setValues($combination);

            // resolve the target path
            $target = rtrim($this->basePath, '/') . '/' . $asset->getTargetPath();
            $target = str_replace('_controller/', '', $target);
            $target = VarUtils::resolve($target, $asset->getVars(), $asset->getValues());

            if (!is_dir($dir = dirname($target))) {
                if (false === @mkdir($dir, 0777, true)) {
                    throw new \RuntimeException('Unable to create directory ' . $dir);
                }
            }

            if (false === @file_put_contents($target, $asset->dump())) {
                throw new \RuntimeException('Unable to write file ' . $target);
            }
        }
    }
}
