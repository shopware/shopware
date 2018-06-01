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

namespace Shopware\Framework\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Struct\JsonSerializableTrait;
use Symfony\Component\EventDispatcher\Event;

abstract class NestedEvent extends Event implements \JsonSerializable
{
    use JsonSerializableTrait;

    abstract public function getName(): string;

    abstract public function getContext(): Context;

    /**
     * @return NestedEventCollection|null
     */
    public function getEvents(): ?NestedEventCollection
    {
        return null;
    }

    public function getFlatEventList(): NestedEventCollection
    {
        $events[] = $this;

        if (!$nestedEvents = $this->getEvents()) {
            return new NestedEventCollection($events);
        }

        /** @var NestedEvent $event */
        foreach ($nestedEvents as $event) {
            $events[] = $event;
            foreach ($event->getFlatEventList() as $item) {
                $events[] = $item;
            }
        }

        return new NestedEventCollection($events);
    }
}
