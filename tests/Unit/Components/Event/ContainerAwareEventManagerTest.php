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

use Shopware\Components\Plugin\SubscriberInterface;
use PHPUnit\Framework\TestCase;
use Shopware\Components\ContainerAwareEventManager;
use Symfony\Component\DependencyInjection\Container;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class ContainerAwareEventManagerTest extends TestCase
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @var ContainerAwareEventManager
     */
    private $eventManager;

    protected function setUp()
    {
        $this->container = new Container();
        $this->eventManager = new ContainerAwareEventManager($this->container);
    }

    public function testAddAListenerService()
    {
        $service = $this->createMock(Service::class);

        $eventArgs = new \Enlight_Event_EventArgs(['some' => 'args']);

        $service
            ->expects($this->once())
            ->method('onEvent')
            ->with($eventArgs)
        ;

        $this->container->set('service.listener', $service);
        $this->eventManager->addListenerService('onEvent', ['service.listener', 'onEvent']);

        $this->eventManager->notify('onEvent', $eventArgs);
    }

    public function testAddAListenerServiceCallMulitpleTimes()
    {
        $service = $this->createMock(Service::class);

        $eventArgs = new \Enlight_Event_EventArgs(['some' => 'args']);

        $service
            ->expects($this->exactly(2))
            ->method('onEvent')
            ->with($eventArgs)
        ;

        $this->container->set('service.listener', $service);
        $this->eventManager->addListenerService('onEvent', ['service.listener', 'onEvent']);

        $this->eventManager->notify('onEvent', $eventArgs);
        $this->eventManager->notify('onEvent', $eventArgs);
    }

    public function testAddASubscriberService()
    {
        $eventArgs = new \Enlight_Event_EventArgs(['some' => 'args']);

        $service = $this->createMock(SubscriberService::class);
        $service
            ->expects($this->once())
            ->method('onEvent')
            ->with($eventArgs)
        ;
        $service
            ->expects($this->once())
            ->method('onEventWithPriority')
            ->with($eventArgs)
        ;
        $service
            ->expects($this->once())
            ->method('onEventNested')
            ->with($eventArgs)
        ;
        $this->container->set('service.subscriber', $service);

        $this->eventManager->addSubscriberService('service.subscriber', SubscriberService::class);
        $this->eventManager->notify('onEvent', $eventArgs);
        $this->eventManager->notify('onEventWithPriority', $eventArgs);
        $this->eventManager->notify('onEventNested', $eventArgs);
    }

    public function testPreventDuplicateListenerService()
    {
        $eventArgs = new \Enlight_Event_EventArgs(['some' => 'args']);
        $service = $this->createMock(Service::class);
        $service
            ->expects($this->once())
            ->method('onEvent')
            ->with($eventArgs)
        ;

        $this->container->set('service.listener', $service);

        $this->eventManager->addListenerService('onEvent', ['service.listener', 'onEvent'], 5);
        $this->eventManager->addListenerService('onEvent', ['service.listener', 'onEvent'], 10);

        $this->eventManager->notify('onEvent', $eventArgs);
    }

    public function testHasListenersOnLazyLoad()
    {
        //        $eventArgs = new \Enlight_Event_EventArgs(['some' => 'args']);
        $service = $this->createMock(Service::class);

        $this->container->set('service.listener', $service);

        $this->eventManager->addListenerService('onEvent', ['service.listener', 'onEvent']);
        $service
            ->expects($this->once())
            ->method('onEvent')
        ;

        if ($this->eventManager->hasListeners('onEvent')) {
            $this->eventManager->notify('onEvent');
        }
    }

    public function testGetListenersOnLazyLoad()
    {
        $service = $this->createMock(Service::class);
        $this->container->set('service.listener', $service);

        $this->eventManager->addListenerService('onEvent', ['service.listener', 'onEvent']);
        $listeners = $this->eventManager->getAllListeners();

        $this->assertTrue(isset($listeners['onevent']));
        $this->assertCount(1, $this->eventManager->getListeners('onEvent'));
    }

    public function testRemoveAfterDispatch()
    {
        $eventArgs = new \Enlight_Event_EventArgs(['some' => 'args']);

        $service = $this->createMock(Service::class);
        $this->container->set('service.listener', $service);

        $this->eventManager->addListenerService('onEvent', ['service.listener', 'onEvent']);

        $handler = new \Enlight_Event_Handler_Default('onEvent', [$this->container->get('service.listener'), 'onEvent']);

        $this->eventManager->notify('onEvent', $eventArgs);

        $this->eventManager->removeListener($handler);

        $this->assertFalse($this->eventManager->hasListeners('onEvent'));
    }

    public function testRemoveBeforeDispatch()
    {
        $service = $this->createMock(Service::class);
        $this->container->set('service.listener', $service);
        $this->eventManager->addListenerService('onEvent', ['service.listener', 'onEvent']);

        $this->eventManager->removeListener(new \Enlight_Event_Handler_Default('onEvent', [$this->container->get('service.listener'), 'onEvent']));

        $this->assertFalse($this->eventManager->hasListeners('onEvent'));
    }
}

class Service
{
    public function onEvent(\Enlight_Event_EventArgs $e)
    {
    }
}
class SubscriberService implements SubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            'onEvent' => 'onEvent',
            'onEventWithPriority' => ['onEventWithPriority', 10],
            'onEventNested' => [['onEventNested']],
        ];
    }

    public function onEvent(\Enlight_Event_EventArgs $e)
    {
    }

    public function onEventWithPriority(\Enlight_Event_EventArgs $e)
    {
    }

    public function onEventNested(\Enlight_Event_EventArgs $e)
    {
    }
}
