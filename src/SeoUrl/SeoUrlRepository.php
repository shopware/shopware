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

namespace Shopware\SeoUrl;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\SeoUrl\Event\SeoUrlBasicLoadedEvent;
use Shopware\SeoUrl\Loader\SeoUrlBasicLoader;
use Shopware\SeoUrl\Searcher\SeoUrlSearcher;
use Shopware\SeoUrl\Struct\SeoUrlBasicCollection;
use Shopware\SeoUrl\Struct\SeoUrlSearchResult;
use Shopware\SeoUrl\Writer\SeoUrlWriter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SeoUrlRepository
{
    /**
     * @var SeoUrlBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SeoUrlSearcher
     */
    private $searcher;

    /**
     * @var SeoUrlWriter
     */
    private $writer;

    public function __construct(
        SeoUrlBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        SeoUrlSearcher $searcher,
        SeoUrlWriter $writer
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function read(array $uuids, TranslationContext $context): SeoUrlBasicCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            SeoUrlBasicLoadedEvent::NAME,
            new SeoUrlBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): SeoUrlSearchResult
    {
        /** @var SeoUrlSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            SeoUrlBasicLoadedEvent::NAME,
            new SeoUrlBasicLoadedEvent($result, $context)
        );

        return $result;
    }

    public function aggregate(Criteria $criteria, TranslationContext $context): AggregationResult
    {
        $result = $this->searcher->aggregate($criteria, $context);

        return $result;
    }

    public function create(array $seoUrls): void
    {
        $this->writer->create($seoUrls);
    }
}
