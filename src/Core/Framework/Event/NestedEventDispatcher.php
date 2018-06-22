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

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\ORM\Event\EntityWrittenContainerEvent;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NestedEventDispatcher implements NestedEventDispatcherInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function dispatch($eventName, Event $event = null): Event
    {
        if (!$event instanceof EntityWrittenContainerEvent) {
            $event = $this->dispatcher->dispatch($eventName, $event);
        }

        if ($event instanceof NestedEvent && $events = $event->getEvents()) {
            foreach ($events as $nested) {
                $this->dispatch($nested->getName(), $nested);
            }
        }

        if ($event instanceof EntityWrittenContainerEvent) {
            $event = $this->dispatcher->dispatch($eventName, $event);
        }

        return $event;
    }

    public function addListener($eventName, $listener, $priority = 0)
    {
        return $this->dispatcher->addListener($eventName, $listener, $priority);
    }

    public function addSubscriber(EventSubscriberInterface $subscriber)
    {
        return $this->dispatcher->addSubscriber($subscriber);
    }

    public function removeListener($eventName, $listener)
    {
        return $this->dispatcher->removeListener($eventName, $listener);
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber)
    {
        return $this->dispatcher->removeSubscriber($subscriber);
    }

    public function getListeners($eventName = null)
    {
        return $this->dispatcher->getListeners($eventName);
    }

    public function getListenerPriority($eventName, $listener)
    {
        return $this->dispatcher->getListenerPriority($eventName, $listener);
    }

    public function hasListeners($eventName = null)
    {
        return $this->dispatcher->hasListeners($eventName);
    }
}
