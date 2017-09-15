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

namespace Shopware\Shop\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Shopware\Shop\Event\ShopBasicLoadedEvent;
use Shopware\Shop\Event\ShopDetailLoadedEvent;
use Shopware\Shop\Loader\ShopBasicLoader;
use Shopware\Shop\Loader\ShopDetailLoader;
use Shopware\Shop\Searcher\ShopSearcher;
use Shopware\Shop\Searcher\ShopSearchResult;
use Shopware\Shop\Struct\ShopBasicCollection;
use Shopware\Shop\Struct\ShopDetailCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ShopRepository
{
    /**
     * @var ShopDetailLoader
     */
    protected $detailLoader;

    /**
     * @var ShopBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ShopSearcher
     */
    private $searcher;

    public function __construct(
        ShopDetailLoader $detailLoader,
        ShopBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        ShopSearcher $searcher
    ) {
        $this->detailLoader = $detailLoader;
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
    }

    public function readDetail(array $uuids, TranslationContext $context): ShopDetailCollection
    {
        $collection = $this->detailLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            ShopDetailLoadedEvent::NAME,
            new ShopDetailLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function read(array $uuids, TranslationContext $context): ShopBasicCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            ShopBasicLoadedEvent::NAME,
            new ShopBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): ShopSearchResult
    {
        /** @var ShopSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            ShopBasicLoadedEvent::NAME,
            new ShopBasicLoadedEvent($result, $context)
        );

        return $result;
    }

    public function searchUuids(Criteria $criteria, TranslationContext $context): UuidSearchResult
    {
        return $this->searcher->searchUuids($criteria, $context);
    }

    public function aggregate(Criteria $criteria, TranslationContext $context): AggregationResult
    {
        $result = $this->searcher->aggregate($criteria, $context);

        return $result;
    }
}
