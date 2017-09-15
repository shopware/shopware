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

namespace Shopware\ShippingMethodPrice\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Shopware\ShippingMethodPrice\Event\ShippingMethodPriceBasicLoadedEvent;
use Shopware\ShippingMethodPrice\Loader\ShippingMethodPriceBasicLoader;
use Shopware\ShippingMethodPrice\Searcher\ShippingMethodPriceSearcher;
use Shopware\ShippingMethodPrice\Searcher\ShippingMethodPriceSearchResult;
use Shopware\ShippingMethodPrice\Struct\ShippingMethodPriceBasicCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ShippingMethodPriceRepository
{
    /**
     * @var ShippingMethodPriceBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ShippingMethodPriceSearcher
     */
    private $searcher;

    public function __construct(
        ShippingMethodPriceBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        ShippingMethodPriceSearcher $searcher
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
    }

    public function read(array $uuids, TranslationContext $context): ShippingMethodPriceBasicCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            ShippingMethodPriceBasicLoadedEvent::NAME,
            new ShippingMethodPriceBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): ShippingMethodPriceSearchResult
    {
        /** @var ShippingMethodPriceSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            ShippingMethodPriceBasicLoadedEvent::NAME,
            new ShippingMethodPriceBasicLoadedEvent($result, $context)
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
