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

namespace Shopware\Tests\Unit\Components;

use PHPUnit\Framework\TestCase;

interface MyInterface
{
    public function myPublic($bar, $foo = 'bar');
}

interface MyReferenceInterface
{
    public function myPublic(&$bar, $foo);
}

class MyBasicTestClass implements MyInterface
{
    public function myPublic($bar, $foo = 'bar')
    {
        return $bar . $foo;
    }

    protected function myProtected($bar)
    {
    }
}

class MyReferenceTestClass implements MyReferenceInterface
{
    public function myPublic(&$bar, $foo)
    {
        return $bar . $foo;
    }
}

class TestProxyFactory extends \Enlight_Hook_ProxyFactory
{
    public function __construct(\Enlight_Hook_HookManager $hookManager, $proxyNamespace)
    {
        $this->hookManager = $hookManager;
        $this->proxyNamespace = $proxyNamespace;
    }
}

class EnlightHookProxyFactoryTest extends TestCase
{
    private $proxyFactory;

    public function setUp()
    {
        /** @var \Enlight_Hook_HookManager $SUT */
        $hookManager = $this->createConfiguredMock(\Enlight_Hook_HookManager::class, [
            'hasHooks' => true,
        ]);

        $this->proxyFactory = new TestProxyFactory($hookManager, 'ShopwareTests');
    }

    public function testGenerateBasicProxyClass()
    {
        $generatedClass = $this->invokeMethod($this->proxyFactory, 'generateProxyClass', [MyBasicTestClass::class]);
        $expectedClass = <<<'EOT'
<?php
class ShopwareTests_ShopwareTestsUnitComponentsMyBasicTestClassProxy extends Shopware\Tests\Unit\Components\MyBasicTestClass implements Enlight_Hook_Proxy
{
    public function executeParent($method, $args = array())
    {
        return call_user_func_array(array($this, 'parent::' . $method), $args);
    }

    public static function getHookMethods()
    {
        return array (  0 => 'myPublic',  1 => 'myProtected',);
    }
    
    public function myPublic($bar, $foo = 'bar')
    {
        return Shopware()->Hooks()->executeHooks(
            $this, 'myPublic', array('bar'=>$bar, 'foo'=>$foo)
        );
    }

    protected function myProtected($bar)
    {
        return Shopware()->Hooks()->executeHooks(
            $this, 'myProtected', array('bar'=>$bar)
        );
    }

}

EOT;
        $this->assertSame($expectedClass, $generatedClass);
    }

    public function testGenerateProxyClassWithReferenceParameter()
    {
        $generatedClass = $this->invokeMethod($this->proxyFactory, 'generateProxyClass', [MyReferenceTestClass::class]);
        $expectedClass = <<<'EOT'
<?php
class ShopwareTests_ShopwareTestsUnitComponentsMyReferenceTestClassProxy extends Shopware\Tests\Unit\Components\MyReferenceTestClass implements Enlight_Hook_Proxy
{
    public function executeParent($method, $args = array())
    {
        return call_user_func_array(array($this, 'parent::' . $method), $args);
    }

    public static function getHookMethods()
    {
        return array (  0 => 'myPublic',);
    }
    
    public function myPublic(&$bar, $foo)
    {
        return Shopware()->Hooks()->executeHooks(
            $this, 'myPublic', array('bar'=>&$bar, 'foo'=>$foo)
        );
    }

}

EOT;
        $this->assertSame($expectedClass, $generatedClass);
    }

    private function invokeMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
