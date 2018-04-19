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

namespace Shopware\Shop\Gateway;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Shop\Struct\ShopSearchResult;
use Shopware\Search\Criteria;
use Shopware\Shop\Event\ShopSearchResultEvent;
use Shopware\Shop\Event\ShopsLoadedEvent;
use Shopware\Shop\Struct\ShopCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ShopRepository
{
    const FETCH_IDENTITY = 'identity';

    const FETCH_DETAIL = 'detail';

    /**
     * @var ShopReader
     */
    private $reader;

    /**
     * @var ShopSearcher
     */
    private $searcher;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(ShopReader $reader, ShopSearcher $searcher, EventDispatcherInterface $eventDispatcher)
    {
        $this->reader = $reader;
        $this->searcher = $searcher;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function read(array $ids, TranslationContext $context, string $fetchMode = self::FETCH_IDENTITY): ShopCollection
    {
        switch ($fetchMode) {
            case self::FETCH_IDENTITY:


        }
        $collection = $this->reader->read($ids, $context);

        $this->eventDispatcher->dispatch(
            ShopsLoadedEvent::NAME,
            new ShopsLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): ShopSearchResult
    {
        /** @var ShopSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            ShopSearchResultEvent::NAME,
            new ShopSearchResultEvent($result, $criteria, $context)
        );

        return $result;
    }
}
