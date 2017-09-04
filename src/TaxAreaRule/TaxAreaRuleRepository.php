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

namespace Shopware\TaxAreaRule;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\TaxAreaRule\Event\TaxAreaRuleBasicLoadedEvent;
use Shopware\TaxAreaRule\Loader\TaxAreaRuleBasicLoader;
use Shopware\TaxAreaRule\Searcher\TaxAreaRuleSearcher;
use Shopware\TaxAreaRule\Struct\TaxAreaRuleBasicCollection;
use Shopware\TaxAreaRule\Struct\TaxAreaRuleSearchResult;
use Shopware\TaxAreaRule\Writer\TaxAreaRuleWriter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TaxAreaRuleRepository
{
    /**
     * @var TaxAreaRuleBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var TaxAreaRuleSearcher
     */
    private $searcher;

    /**
     * @var TaxAreaRuleWriter
     */
    private $writer;

    public function __construct(
        TaxAreaRuleBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        TaxAreaRuleSearcher $searcher,
        TaxAreaRuleWriter $writer
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function read(array $uuids, TranslationContext $context): TaxAreaRuleBasicCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            TaxAreaRuleBasicLoadedEvent::NAME,
            new TaxAreaRuleBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): TaxAreaRuleSearchResult
    {
        /** @var TaxAreaRuleSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            TaxAreaRuleBasicLoadedEvent::NAME,
            new TaxAreaRuleBasicLoadedEvent($result, $context)
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
