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

namespace Shopware\Tests\Unit\Components\Plugin;

use PHPUnit\Framework\TestCase;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;

class InstallContextTest extends TestCase
{
    public function testFrontendCaches()
    {
        $entity = new \Shopware\Models\Plugin\Plugin();
        $context = new ActivateContext($entity, \Shopware::VERSION, '1.0.0');
        $plugin = new MyPlugin(true);
        $plugin->activate($context);

        $this->assertArrayHasKey('cache', $context->getScheduled());
        $this->assertNotEmpty($context->getScheduled()['cache']);
    }

    public function testMessage()
    {
        $entity = new \Shopware\Models\Plugin\Plugin();
        $context = new DeactivateContext($entity, \Shopware::VERSION, '1.0.0');
        $plugin = new MyPlugin(true);

        $plugin->deactivate($context);
        $this->assertArrayHasKey('message', $context->getScheduled());
        $this->assertEquals($context->getScheduled()['message'], 'Clear the caches');
    }

    public function testCacheCombination()
    {
        $entity = new \Shopware\Models\Plugin\Plugin();
        $context = new InstallContext($entity, \Shopware::VERSION, '1.0.0');
        $plugin = new MyPlugin(true);

        $plugin->install($context);
        $this->assertArrayHasKey('cache', $context->getScheduled());
        $this->assertNotEmpty($context->getScheduled()['cache']);
        $this->assertCount(count(InstallContext::CACHE_LIST_ALL), $context->getScheduled()['cache']);
    }

    public function testDefault()
    {
        $entity = new \Shopware\Models\Plugin\Plugin();
        $context = new UninstallContext($entity, \Shopware::VERSION, '1.0.0', true);
        $plugin = new MyPlugin(true);

        $plugin->uninstall($context);
        $this->assertArrayHasKey('cache', $context->getScheduled());
        $this->assertEquals(InstallContext::CACHE_LIST_DEFAULT, $context->getScheduled()['cache']);
    }
}

class MyPlugin extends \Shopware\Components\Plugin
{
    public function activate(ActivateContext $context)
    {
        $context->scheduleClearCache(InstallContext::CACHE_LIST_FRONTEND);
    }

    public function deactivate(DeactivateContext $context)
    {
        $context->scheduleMessage('Clear the caches');
    }

    public function install(InstallContext $context)
    {
        $context->scheduleClearCache(InstallContext::CACHE_LIST_FRONTEND);
        $context->scheduleClearCache(InstallContext::CACHE_LIST_DEFAULT);
    }
}
