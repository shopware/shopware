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

namespace Shopware\Category\Gateway;

use Shopware\Category\Event\CategoryIdentityLoadedEvent;
use Shopware\Category\Event\CategoryLoadedEvent;
use Shopware\Category\Struct\CategoryIdentityCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CategoryRepository
{
    const FETCH_IDENTITY = 'identity';

    const FETCH_DETAIL = 'detail';

    /**
     * @var CategoryReader
     */
    private $reader;

    /**
     * @var CategorySearcher
     */
    private $searcher;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(CategoryReader $reader, CategorySearcher $searcher, EventDispatcherInterface $eventDispatcher)
    {
        $this->reader = $reader;
        $this->searcher = $searcher;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function search(Criteria $criteria, TranslationContext $context): CategorySearchResult
    {
        /** @var CategorySearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            CategoryIdentityLoadedEvent::NAME,
            new CategoryIdentityLoadedEvent($result, $context)
        );

        return $result;
    }

    public function read(array $ids, TranslationContext $context, string $fetchMode = self::FETCH_IDENTITY): CategoryIdentityCollection
    {
        switch ($fetchMode) {
            case self::FETCH_DETAIL:
                $categories = $this->reader->read($ids, $context);

                $this->eventDispatcher->dispatch(
                    CategoryIdentityLoadedEvent::NAME,
                    new CategoryIdentityLoadedEvent($categories, $context)
                );

                $this->eventDispatcher->dispatch(
                    CategoryLoadedEvent::NAME,
                    new CategoryLoadedEvent($categories, $context)
                );

                return $categories;

            default:
            case self::FETCH_IDENTITY:
                $identities = $this->reader->readIdentities($ids, $context);

                $this->eventDispatcher->dispatch(
                    CategoryIdentityLoadedEvent::NAME,
                    new CategoryIdentityLoadedEvent($identities, $context)
                );

                return $identities;
        }
    }
}
