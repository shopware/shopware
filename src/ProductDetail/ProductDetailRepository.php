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

namespace Shopware\ProductDetail;

use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductDetail\Event\ProductDetailBasicLoadedEvent;
use Shopware\ProductDetail\Event\ProductDetailDetailLoadedEvent;
use Shopware\ProductDetail\Loader\ProductDetailBasicLoader;
use Shopware\ProductDetail\Loader\ProductDetailDetailLoader;
use Shopware\ProductDetail\Searcher\ProductDetailSearcher;
use Shopware\ProductDetail\Struct\ProductDetailBasicCollection;
use Shopware\ProductDetail\Struct\ProductDetailDetailCollection;
use Shopware\ProductDetail\Struct\ProductDetailSearchResult;
use Shopware\ProductDetail\Writer\ProductDetailWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductDetailRepository
{
    /**
     * @var ProductDetailDetailLoader
     */
    protected $detailLoader;
    /**
     * @var ProductDetailBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ProductDetailSearcher
     */
    private $searcher;

    /**
     * @var ProductDetailWriter
     */
    private $writer;

    public function __construct(
        ProductDetailBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        ProductDetailSearcher $searcher,
        ProductDetailWriter $writer,
ProductDetailDetailLoader $detailLoader
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
        $this->detailLoader = $detailLoader;
    }

    public function readDetail(array $uuids, TranslationContext $context): ProductDetailDetailCollection
    {
        $collection = $this->detailLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            ProductDetailDetailLoadedEvent::NAME,
            new ProductDetailDetailLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function read(array $uuids, TranslationContext $context): ProductDetailBasicCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            ProductDetailBasicLoadedEvent::NAME,
            new ProductDetailBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): ProductDetailSearchResult
    {
        /** @var ProductDetailSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            ProductDetailBasicLoadedEvent::NAME,
            new ProductDetailBasicLoadedEvent($result, $context)
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
