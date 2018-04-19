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

namespace Shopware\Currency\Gateway;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Struct\CurrencyCollection;
use Shopware\Currency\Struct\CurrencySearchResult;
use Shopware\Search\Criteria;

class CurrencyRepository
{
    /**
     * @var CurrencyReader
     */
    private $reader;

    /**
     * @var CurrencySearcher
     */
    private $searcher;

    public function __construct(CurrencyReader $reader, CurrencySearcher $searcher)
    {
        $this->reader = $reader;
        $this->searcher = $searcher;
    }

    public function read(array $ids, TranslationContext $context): CurrencyCollection
    {
        return $this->reader->read($ids, $context);
    }

    public function search(Criteria $criteria, TranslationContext $context): CurrencySearchResult
    {
        return $this->searcher->search($criteria, $context);
    }
}
