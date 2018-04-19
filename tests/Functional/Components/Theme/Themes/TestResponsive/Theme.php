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

namespace Shopware\Themes\TestResponsive;

use Shopware\Components\Theme\ConfigSet;

class Theme extends \Shopware\Components\Theme
{
    protected $extend = 'TestBare';

    protected $inheritanceConfig = true;

    protected $javascript = ['responsive_1.js', 'responsive_2.js'];

    protected $css = ['responsive_1.css', 'responsive_2.css'];

    protected $injectBeforePlugins = false;

    public function createConfig(\Shopware\Components\Form\Container\TabContainer $container)
    {
        $container->addTab(new \Shopware\Components\Form\Container\Tab('responsive', 'responsive'));
    }

    public function createConfigSets(\Doctrine\Common\Collections\ArrayCollection $collection)
    {
        $collection->add(new ConfigSet('set1', ['value1' => 1]));
        $collection->add(new ConfigSet('set2', ['value1' => 2]));
    }
}
