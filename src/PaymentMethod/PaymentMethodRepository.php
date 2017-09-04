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

namespace Shopware\PaymentMethod;

use Shopware\Context\Struct\TranslationContext;
use Shopware\PaymentMethod\Event\PaymentMethodBasicLoadedEvent;
use Shopware\PaymentMethod\Loader\PaymentMethodBasicLoader;
use Shopware\PaymentMethod\Searcher\PaymentMethodSearcher;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicCollection;
use Shopware\PaymentMethod\Struct\PaymentMethodSearchResult;
use Shopware\PaymentMethod\Writer\PaymentMethodWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PaymentMethodRepository
{
    /**
     * @var PaymentMethodBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var PaymentMethodSearcher
     */
    private $searcher;

    /**
     * @var PaymentMethodWriter
     */
    private $writer;

    public function __construct(
        PaymentMethodBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        PaymentMethodSearcher $searcher,
        PaymentMethodWriter $writer
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function read(array $uuids, TranslationContext $context): PaymentMethodBasicCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            PaymentMethodBasicLoadedEvent::NAME,
            new PaymentMethodBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): PaymentMethodSearchResult
    {
        /** @var PaymentMethodSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            PaymentMethodBasicLoadedEvent::NAME,
            new PaymentMethodBasicLoadedEvent($result, $context)
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
