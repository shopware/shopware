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

namespace Shopware\ShippingMethod\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Shopware\ShippingMethod\Event\ShippingMethodBasicLoadedEvent;
use Shopware\ShippingMethod\Event\ShippingMethodDetailLoadedEvent;
use Shopware\ShippingMethod\Loader\ShippingMethodBasicLoader;
use Shopware\ShippingMethod\Loader\ShippingMethodDetailLoader;
use Shopware\ShippingMethod\Searcher\ShippingMethodSearcher;
use Shopware\ShippingMethod\Searcher\ShippingMethodSearchResult;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicCollection;
use Shopware\ShippingMethod\Struct\ShippingMethodDetailCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ShippingMethodRepository
{
    /**
     * @var ShippingMethodDetailLoader
     */
    protected $detailLoader;

    /**
     * @var ShippingMethodBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ShippingMethodSearcher
     */
    private $searcher;

    public function __construct(
        ShippingMethodDetailLoader $detailLoader,
        ShippingMethodBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        ShippingMethodSearcher $searcher
    ) {
        $this->detailLoader = $detailLoader;
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
    }

    public function readDetail(array $uuids, TranslationContext $context): ShippingMethodDetailCollection
    {
        $collection = $this->detailLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            ShippingMethodDetailLoadedEvent::NAME,
            new ShippingMethodDetailLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function read(array $uuids, TranslationContext $context): ShippingMethodBasicCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            ShippingMethodBasicLoadedEvent::NAME,
            new ShippingMethodBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): ShippingMethodSearchResult
    {
        /** @var ShippingMethodSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            ShippingMethodBasicLoadedEvent::NAME,
            new ShippingMethodBasicLoadedEvent($result, $context)
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
