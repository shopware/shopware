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

namespace Shopware\ProductStream;

use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductStream\Event\ProductStreamBasicLoadedEvent;
use Shopware\ProductStream\Loader\ProductStreamBasicLoader;
use Shopware\ProductStream\Searcher\ProductStreamSearcher;
use Shopware\ProductStream\Struct\ProductStreamBasicCollection;
use Shopware\ProductStream\Struct\ProductStreamSearchResult;
use Shopware\ProductStream\Writer\ProductStreamWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductStreamRepository
{
    /**
     * @var ProductStreamBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ProductStreamSearcher
     */
    private $searcher;

    /**
     * @var ProductStreamWriter
     */
    private $writer;

    public function __construct(
        ProductStreamBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        ProductStreamSearcher $searcher,
        ProductStreamWriter $writer
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function read(array $uuids, TranslationContext $context): ProductStreamBasicCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            ProductStreamBasicLoadedEvent::NAME,
            new ProductStreamBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): ProductStreamSearchResult
    {
        /** @var ProductStreamSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            ProductStreamBasicLoadedEvent::NAME,
            new ProductStreamBasicLoadedEvent($result, $context)
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
