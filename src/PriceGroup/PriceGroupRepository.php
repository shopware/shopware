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

namespace Shopware\PriceGroup;

use Shopware\Context\Struct\TranslationContext;
use Shopware\PriceGroup\Event\PriceGroupBasicLoadedEvent;
use Shopware\PriceGroup\Event\PriceGroupDetailLoadedEvent;
use Shopware\PriceGroup\Loader\PriceGroupBasicLoader;
use Shopware\PriceGroup\Loader\PriceGroupDetailLoader;
use Shopware\PriceGroup\Searcher\PriceGroupSearcher;
use Shopware\PriceGroup\Struct\PriceGroupBasicCollection;
use Shopware\PriceGroup\Struct\PriceGroupDetailCollection;
use Shopware\PriceGroup\Struct\PriceGroupSearchResult;
use Shopware\PriceGroup\Writer\PriceGroupWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
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

    /**
     * @var PriceGroupWriter
     */
    private $writer;

    public function __construct(
        PriceGroupBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        PriceGroupSearcher $searcher,
        PriceGroupWriter $writer,
PriceGroupDetailLoader $detailLoader
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
        $this->detailLoader = $detailLoader;
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

    public function aggregate(Criteria $criteria, TranslationContext $context): AggregationResult
    {
        $result = $this->searcher->aggregate($criteria, $context);

        return $result;
    }

    public function write(): void
    {
        $this->writer->write();
    }
}
