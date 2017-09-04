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

namespace Shopware\AreaCountryState;

use Shopware\AreaCountryState\Event\AreaCountryStateBasicLoadedEvent;
use Shopware\AreaCountryState\Loader\AreaCountryStateBasicLoader;
use Shopware\AreaCountryState\Searcher\AreaCountryStateSearcher;
use Shopware\AreaCountryState\Struct\AreaCountryStateBasicCollection;
use Shopware\AreaCountryState\Struct\AreaCountryStateSearchResult;
use Shopware\AreaCountryState\Writer\AreaCountryStateWriter;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AreaCountryStateRepository
{
    /**
     * @var AreaCountryStateBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AreaCountryStateSearcher
     */
    private $searcher;

    /**
     * @var AreaCountryStateWriter
     */
    private $writer;

    public function __construct(
        AreaCountryStateBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        AreaCountryStateSearcher $searcher,
        AreaCountryStateWriter $writer
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function read(array $uuids, TranslationContext $context): AreaCountryStateBasicCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            AreaCountryStateBasicLoadedEvent::NAME,
            new AreaCountryStateBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): AreaCountryStateSearchResult
    {
        /** @var AreaCountryStateSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            AreaCountryStateBasicLoadedEvent::NAME,
            new AreaCountryStateBasicLoadedEvent($result, $context)
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
