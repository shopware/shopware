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

namespace Shopware\ProductManufacturer;

use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductManufacturer\Event\ProductManufacturerBasicLoadedEvent;
use Shopware\ProductManufacturer\Loader\ProductManufacturerBasicLoader;
use Shopware\ProductManufacturer\Searcher\ProductManufacturerSearcher;
use Shopware\ProductManufacturer\Struct\ProductManufacturerBasicCollection;
use Shopware\ProductManufacturer\Struct\ProductManufacturerSearchResult;
use Shopware\ProductManufacturer\Writer\ProductManufacturerWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductManufacturerRepository
{
    /**
     * @var ProductManufacturerBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ProductManufacturerSearcher
     */
    private $searcher;

    /**
     * @var ProductManufacturerWriter
     */
    private $writer;

    public function __construct(
        ProductManufacturerBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        ProductManufacturerSearcher $searcher,
        ProductManufacturerWriter $writer
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function read(array $uuids, TranslationContext $context): ProductManufacturerBasicCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            ProductManufacturerBasicLoadedEvent::NAME,
            new ProductManufacturerBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): ProductManufacturerSearchResult
    {
        /** @var ProductManufacturerSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            ProductManufacturerBasicLoadedEvent::NAME,
            new ProductManufacturerBasicLoadedEvent($result, $context)
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
