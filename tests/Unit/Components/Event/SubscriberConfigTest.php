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

namespace Shopware\Tests\Unit\Components\Event;

use PHPUnit\Framework\TestCase;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class SubscriberConfigTest extends TestCase
{
    /**
     * @var \Enlight_Event_Subscriber_Config
     */
    protected $eventManager;

    public function setUp()
    {
        $this->eventManager = new \Enlight_Event_Subscriber_Config('test');
    }

    public function testCanCreateInstance()
    {
        $this->assertInstanceOf(\Enlight_Event_Subscriber_Config::class, $this->eventManager);
        $this->assertInstanceOf(\Enlight_Event_Subscriber::class, $this->eventManager);
    }

    public function testAddSubscriber()
    {
        // Add to subscribers
        $handler0 = new \Enlight_Event_Handler_Default(
            'Example',
            function ($args) {
                return 'foo';
            }
        );
        $this->eventManager->registerListener($handler0);

        $handler1 = new \Enlight_Event_Handler_Default(
            'Example',
            function ($args) {
                return 'bar';
            }
        );
        $this->eventManager->registerListener($handler1);

        $result = $this->eventManager->getListeners();

        $this->assertCount(2, $result);
        $this->assertEquals('foo', $result[0]->execute(new \Enlight_Event_EventArgs()));
        $this->assertEquals('bar', $result[1]->execute(new \Enlight_Event_EventArgs()));
    }

    public function testRemoveSubscriber()
    {
        // Add to subscribers
        $handler0 = new \Enlight_Event_Handler_Default(
            'Example',
            function ($args) {
                return 'foo';
            }
        );
        $this->eventManager->registerListener($handler0);

        $handler1 = new \Enlight_Event_Handler_Default(
            'Example',
            function ($args) {
                return 'bar';
            }
        );
        $this->eventManager->registerListener($handler1);

        // Remove first subscriber
        $this->eventManager->removeListener($handler0);

        $result = $this->eventManager->getListeners();

        // Only the second one should be left
        $this->assertCount(1, $result);
        $this->assertEquals('bar', $result[0]->execute(new \Enlight_Event_EventArgs()));
    }
}
