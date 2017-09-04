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

namespace Shopware\Media;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Media\Event\MediaBasicLoadedEvent;
use Shopware\Media\Loader\MediaBasicLoader;
use Shopware\Media\Searcher\MediaSearcher;
use Shopware\Media\Struct\MediaBasicCollection;
use Shopware\Media\Struct\MediaSearchResult;
use Shopware\Media\Writer\MediaWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MediaRepository
{
    /**
     * @var MediaBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var MediaSearcher
     */
    private $searcher;

    /**
     * @var MediaWriter
     */
    private $writer;

    public function __construct(
        MediaBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        MediaSearcher $searcher,
        MediaWriter $writer
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function read(array $uuids, TranslationContext $context): MediaBasicCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            MediaBasicLoadedEvent::NAME,
            new MediaBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): MediaSearchResult
    {
        /** @var MediaSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            MediaBasicLoadedEvent::NAME,
            new MediaBasicLoadedEvent($result, $context)
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
