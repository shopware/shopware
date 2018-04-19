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

namespace Shopware\Tests\Unit\Components\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Shopware\Bundle\ControllerBundle\DependencyInjection\Compiler\RegisterControllerCompilerPass;
use Shopware\Tests\Unit\Components\DependencyInjection\Compiler\RegisterControllerExamplePlugins\BackendController\BackendController;
use Shopware\Tests\Unit\Components\DependencyInjection\Compiler\RegisterControllerExamplePlugins\DifferentController\DifferentController;
use Shopware\Tests\Unit\Components\DependencyInjection\Compiler\RegisterControllerExamplePlugins\NoneController\NoneController;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.com)
 */
class RegisterControllerCompilerPassTest extends TestCase
{
    public function testWithNoneActivePlugins()
    {
        $plugins = [];
        $container = new ContainerBuilder();
        $compilerPass = new RegisterControllerCompilerPass($plugins);
        $compilerPass->process($container);

        $this->assertFalse(
            $container->hasDefinition('shopware.generic_controller_listener')
        );
    }

    public function testWithPluginsWithoutControllers()
    {
        $plugins = [new NoneController(true)];
        $container = new ContainerBuilder();

        $compilerPass = new RegisterControllerCompilerPass($plugins);
        $compilerPass->process($container);

        $this->assertFalse(
            $container->hasDefinition('shopware.generic_controller_listener')
        );
    }

    public function testWithBackendController()
    {
        $plugins = [new BackendController(true)];
        $container = new ContainerBuilder();

        $compilerPass = new RegisterControllerCompilerPass($plugins);
        $compilerPass->process($container);

        $this->assertTrue(
            $container->hasDefinition('shopware.generic_controller_listener')
        );

        $definition = $container->getDefinition('shopware.generic_controller_listener');
        $this->assertTrue($definition->hasTag('shopware.event_listener'));

        $this->assertCount(1, $definition->getTag('shopware.event_listener'));
    }

    public function testWithMultiplePlugins()
    {
        $plugins = [new BackendController(true), new DifferentController(true)];
        $container = new ContainerBuilder();

        $compilerPass = new RegisterControllerCompilerPass($plugins);
        $compilerPass->process($container);

        $this->assertTrue(
            $container->hasDefinition('shopware.generic_controller_listener')
        );

        $definition = $container->getDefinition('shopware.generic_controller_listener');
        $this->assertTrue($definition->hasTag('shopware.event_listener'));
        $this->assertCount(5, $definition->getTag('shopware.event_listener'));
    }
}
