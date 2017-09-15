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

namespace Shopware\AreaCountry\Repository;

use Shopware\AreaCountry\Event\AreaCountryBasicLoadedEvent;
use Shopware\AreaCountry\Event\AreaCountryDetailLoadedEvent;
use Shopware\AreaCountry\Loader\AreaCountryBasicLoader;
use Shopware\AreaCountry\Loader\AreaCountryDetailLoader;
use Shopware\AreaCountry\Searcher\AreaCountrySearcher;
use Shopware\AreaCountry\Searcher\AreaCountrySearchResult;
use Shopware\AreaCountry\Struct\AreaCountryBasicCollection;
use Shopware\AreaCountry\Struct\AreaCountryDetailCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AreaCountryRepository
{
    /**
     * @var AreaCountryDetailLoader
     */
    protected $detailLoader;

    /**
     * @var AreaCountryBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AreaCountrySearcher
     */
    private $searcher;

    public function __construct(
        AreaCountryDetailLoader $detailLoader,
        AreaCountryBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        AreaCountrySearcher $searcher
    ) {
        $this->detailLoader = $detailLoader;
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
    }

    public function readDetail(array $uuids, TranslationContext $context): AreaCountryDetailCollection
    {
        $collection = $this->detailLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            AreaCountryDetailLoadedEvent::NAME,
            new AreaCountryDetailLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function read(array $uuids, TranslationContext $context): AreaCountryBasicCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            AreaCountryBasicLoadedEvent::NAME,
            new AreaCountryBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): AreaCountrySearchResult
    {
        /** @var AreaCountrySearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            AreaCountryBasicLoadedEvent::NAME,
            new AreaCountryBasicLoadedEvent($result, $context)
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
