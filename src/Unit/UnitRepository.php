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

namespace Shopware\Unit;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Unit\Event\UnitBasicLoadedEvent;
use Shopware\Unit\Loader\UnitBasicLoader;
use Shopware\Unit\Searcher\UnitSearcher;
use Shopware\Unit\Struct\UnitBasicCollection;
use Shopware\Unit\Struct\UnitSearchResult;
use Shopware\Unit\Writer\UnitWriter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class UnitRepository
{
    /**
     * @var UnitBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var UnitSearcher
     */
    private $searcher;

    /**
     * @var UnitWriter
     */
    private $writer;

    public function __construct(
        UnitBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        UnitSearcher $searcher,
        UnitWriter $writer
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function read(array $uuids, TranslationContext $context): UnitBasicCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            UnitBasicLoadedEvent::NAME,
            new UnitBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): UnitSearchResult
    {
        /** @var UnitSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            UnitBasicLoadedEvent::NAME,
            new UnitBasicLoadedEvent($result, $context)
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
