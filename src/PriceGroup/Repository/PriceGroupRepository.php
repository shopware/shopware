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

namespace Shopware\PriceGroup\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\PriceGroup\Event\PriceGroupBasicLoadedEvent;
use Shopware\PriceGroup\Event\PriceGroupDetailLoadedEvent;
use Shopware\PriceGroup\Loader\PriceGroupBasicLoader;
use Shopware\PriceGroup\Loader\PriceGroupDetailLoader;
use Shopware\PriceGroup\Searcher\PriceGroupSearcher;
use Shopware\PriceGroup\Searcher\PriceGroupSearchResult;
use Shopware\PriceGroup\Struct\PriceGroupBasicCollection;
use Shopware\PriceGroup\Struct\PriceGroupDetailCollection;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PriceGroupRepository
{
    /**
     * @var PriceGroupDetailLoader
     */
    protected $detailLoader;

    /**
     * @var PriceGroupBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var PriceGroupSearcher
     */
    private $searcher;

    public function __construct(
        PriceGroupDetailLoader $detailLoader,
        PriceGroupBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        PriceGroupSearcher $searcher
    ) {
        $this->detailLoader = $detailLoader;
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
    }

    public function readDetail(array $uuids, TranslationContext $context): PriceGroupDetailCollection
    {
        $collection = $this->detailLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            PriceGroupDetailLoadedEvent::NAME,
            new PriceGroupDetailLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function read(array $uuids, TranslationContext $context): PriceGroupBasicCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            PriceGroupBasicLoadedEvent::NAME,
            new PriceGroupBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): PriceGroupSearchResult
    {
        /** @var PriceGroupSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            PriceGroupBasicLoadedEvent::NAME,
            new PriceGroupBasicLoadedEvent($result, $context)
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
