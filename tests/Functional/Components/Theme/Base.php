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

namespace Shopware\Tests\Functional\Components\Theme;

use Shopware\Components\Theme\Configurator;
use Shopware\Models\Shop\Template;

/**
 * Class Shopware_Tests_Components_Theme_Base
 */
class Base extends \Enlight_Components_Test_TestCase
{
    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getEntityManager()
    {
        return $this->createMock(\Shopware\Components\Model\ModelManager::class);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getEventManager()
    {
        return $this->createMock(\Enlight_Event_EventManager::class);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getPathResolver()
    {
        return $this->createMock(\Shopware\Components\Theme\PathResolver::class);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getUtilClass()
    {
        return $this->createMock(\Shopware\Components\Theme\Util::class);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getConfigurator()
    {
        return $this->createMock(Configurator::class);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getFormPersister()
    {
        return $this->createMock(\Shopware\Components\Form\Persister\Theme::class);
    }

    /**
     * @return \Shopware\Themes\TestBare\Theme
     */
    protected function getBareTheme()
    {
        require_once __DIR__ . '/Themes/TestBare/Theme.php';

        return new \Shopware\Themes\TestBare\Theme();
    }

    /**
     * @return \Shopware\Themes\TestResponsive\Theme
     */
    protected function getResponsiveTheme()
    {
        require_once __DIR__ . '/Themes/TestResponsive/Theme.php';

        return new \Shopware\Themes\TestResponsive\Theme();
    }

    /**
     * @return Template
     */
    protected function getTemplate()
    {
        return $this->createMock(Template::class);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getShopRepository()
    {
        return $this->createMock(\Shopware\Models\Shop\Repository::class);
    }

    protected function getSnippetHandler()
    {
        return $this->createMock(\Shopware\Components\Snippet\DatabaseHandler::class);
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object &$object    Instantiated object that we will run method on
     * @param string $methodName Method name to call
     * @param array  $parameters array of parameters to pass into method
     *
     * @return mixed method return
     */
    protected function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
