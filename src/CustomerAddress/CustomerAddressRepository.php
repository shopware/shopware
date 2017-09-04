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

namespace Shopware\CustomerAddress;

use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerAddress\Event\CustomerAddressBasicLoadedEvent;
use Shopware\CustomerAddress\Loader\CustomerAddressBasicLoader;
use Shopware\CustomerAddress\Searcher\CustomerAddressSearcher;
use Shopware\CustomerAddress\Struct\CustomerAddressBasicCollection;
use Shopware\CustomerAddress\Struct\CustomerAddressSearchResult;
use Shopware\CustomerAddress\Writer\CustomerAddressWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CustomerAddressRepository
{
    /**
     * @var CustomerAddressBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CustomerAddressSearcher
     */
    private $searcher;

    /**
     * @var CustomerAddressWriter
     */
    private $writer;

    public function __construct(
        CustomerAddressBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        CustomerAddressSearcher $searcher,
        CustomerAddressWriter $writer
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function read(array $uuids, TranslationContext $context): CustomerAddressBasicCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            CustomerAddressBasicLoadedEvent::NAME,
            new CustomerAddressBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): CustomerAddressSearchResult
    {
        /** @var CustomerAddressSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            CustomerAddressBasicLoadedEvent::NAME,
            new CustomerAddressBasicLoadedEvent($result, $context)
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
