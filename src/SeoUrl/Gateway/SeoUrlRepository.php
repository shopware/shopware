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

namespace Shopware\SeoUrl\Gateway;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Criteria;
use Shopware\SeoUrl\Struct\SeoUrlCollection;

class SeoUrlRepository
{
    /**
     * @var SeoUrlReader
     */
    private $reader;

    /**
     * @var SeoUrlSearcher
     */
    private $searcher;

    /**
     * @var SeoUrlWriter
     */
    private $writer;

    public function __construct(SeoUrlReader $reader, SeoUrlSearcher $searcher, SeoUrlWriter $writer)
    {
        $this->reader = $reader;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function read(array $ids, TranslationContext $context): SeoUrlCollection
    {
        return $this->reader->read($ids, $context);
    }

    public function search(Criteria $criteria, TranslationContext $context): SeoUrlSearchResult
    {
        return $this->searcher->search($criteria, $context);
    }

    public function create(array $urls): void
    {
        $this->writer->create($urls);
    }

    public function delete(array $ids): void
    {
        $this->writer->delete($ids);
    }
}
