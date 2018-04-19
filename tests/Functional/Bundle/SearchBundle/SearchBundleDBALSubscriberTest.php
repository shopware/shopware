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

namespace Shopware\Tests\Functional\Bundle\SearchBundle;

use Shopware\Search\Condition\CategoryCondition;
use Shopware\Bundle\SearchBundle\CriteriaRequestHandler\CoreCriteriaRequestHandler;
use Shopware\Product\Gateway\Handler\CategoryConditionHandler;
use Shopware\Bundle\SearchBundleDBAL\SearchBundleDBALSubscriber;
use Shopware\Bundle\SearchBundleDBAL\SortingHandler\PopularitySortingHandler;
use Shopware\Bundle\SearchBundleDBAL\SortingHandler\ProductNameSortingHandler;

class SearchBundleDBALSubscriberTest extends \PHPUnit\Framework\TestCase
{
    public function testValidCreate()
    {
        $criteriaRequestHandler = $this->createMock(CoreCriteriaRequestHandler::class);

        $subscriber = new SearchBundleDBALSubscriber([
            new CategoryConditionHandler(),
            new PopularitySortingHandler(),
            $criteriaRequestHandler,
        ]);

        $this->assertInstanceOf('\Shopware\Bundle\SearchBundleDBAL\SearchBundleDBALSubscriber', $subscriber);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Unknown handler class  detected
     */
    public function testNestedArrays()
    {
        new SearchBundleDBALSubscriber([
            [new CategoryConditionHandler(), new CategoryConditionHandler()],
            new PopularitySortingHandler(),
            new ProductNameSortingHandler(),
        ]);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage No handlers provided in \Shopware\Bundle\SearchBundleDBAL\SearchBundleDBALSubscriber
     */
    public function testEmptyArray()
    {
        new SearchBundleDBALSubscriber([]);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Unknown handler class Shopware\Search\Condition\CategoryCondition detected
     */
    public function testInvalidClass()
    {
        new SearchBundleDBALSubscriber([
            new CategoryCondition([1, 2]),
            new CategoryConditionHandler(),
        ]);
    }
}
