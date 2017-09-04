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

namespace Shopware\Holiday;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Holiday\Event\HolidayBasicLoadedEvent;
use Shopware\Holiday\Loader\HolidayBasicLoader;
use Shopware\Holiday\Searcher\HolidaySearcher;
use Shopware\Holiday\Struct\HolidayBasicCollection;
use Shopware\Holiday\Struct\HolidaySearchResult;
use Shopware\Holiday\Writer\HolidayWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class HolidayRepository
{
    /**
     * @var HolidayBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var HolidaySearcher
     */
    private $searcher;

    /**
     * @var HolidayWriter
     */
    private $writer;

    public function __construct(
        HolidayBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        HolidaySearcher $searcher,
        HolidayWriter $writer
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function read(array $uuids, TranslationContext $context): HolidayBasicCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            HolidayBasicLoadedEvent::NAME,
            new HolidayBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): HolidaySearchResult
    {
        /** @var HolidaySearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            HolidayBasicLoadedEvent::NAME,
            new HolidayBasicLoadedEvent($result, $context)
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
