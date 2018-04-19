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

namespace Shopware\Tests\Functional\Plugins\Core\MarketingAggregate\Components;

use Shopware\Tests\Functional\Plugins\Core\MarketingAggregate\AbstractMarketing;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class TopSellerTest extends AbstractMarketing
{
    public function testInitTopSeller()
    {
        $this->resetTopSeller();

        $this->assertCount(0, $this->getAllTopSeller());

        $this->TopSeller()->initTopSeller(50);

        $this->assertCount(50, $this->getAllTopSeller());

        $this->TopSeller()->initTopSeller();

        $this->assertCount(
            count($this->getAllArticles()),
            $this->getAllTopSeller()
        );
    }

    public function testUpdateElapsedTopSeller()
    {
        //init top seller to be sure that all articles has a row
        $this->resetTopSeller();
        $this->TopSeller()->initTopSeller();

        $this->Db()->query("UPDATE s_articles_top_seller_ro SET last_cleared = '2010-01-01'");

        //check if the update script was successfully
        $topSeller = $this->getAllTopSeller(" WHERE last_cleared > '2010-01-01' ");
        $this->assertCount(0, $topSeller);

        //update only 50 top seller articles to test the limit function
        $this->TopSeller()->updateElapsedTopSeller(50);

        //check if only 50 top seller was updated.
        $topSeller = $this->getAllTopSeller(" WHERE last_cleared > '2010-01-01' ");
        $this->assertCount(
            50,
            $topSeller
        );

        //now we can update the all other top seller data
        $this->TopSeller()->updateElapsedTopSeller();
        $this->assertCount(
            count($this->getAllTopSeller()),
            $this->getAllTopSeller(" WHERE last_cleared > '2010-01-01' ")
        );
    }

    public function testIncrementTopSeller()
    {
        $this->resetTopSeller();
        $this->TopSeller()->initTopSeller();

        $topSeller = $this->getAllTopSeller(' LIMIT 1 ');
        $topSeller = $topSeller[0];
        $initialValue = $topSeller['sales'];

        $this->TopSeller()->incrementTopSeller($topSeller['article_id'], 10);

        $topSeller = $this->getAllTopSeller(' WHERE article_id = ' . $topSeller['article_id']);
        $this->assertCount(1, $topSeller);
        $topSeller = $topSeller[0];

        $this->assertEquals($initialValue + 10, $topSeller['sales']);
    }

    public function testRefreshTopSellerForArticleId()
    {
        $this->resetTopSeller();
        $this->TopSeller()->initTopSeller();

        $topSeller = $this->getAllTopSeller(' LIMIT 1 ');
        $topSeller = $topSeller[0];

        $this->resetTopSeller();
        $this->TopSeller()->refreshTopSellerForArticleId($topSeller['article_id']);

        $allTopSeller = $this->getAllTopSeller();
        $this->assertCount(1, $allTopSeller);

        $this->assertArrayEquals($topSeller, $allTopSeller[0], ['article_id', 'sales']);
    }

    /**
     * @group skipElasticSearch
     */
    public function testTopSellerLiveRefresh()
    {
        $this->resetTopSeller();
        $this->TopSeller()->initTopSeller();

        $this->saveConfig('topSellerRefreshStrategy', 3);
        Shopware()->Container()->get('cache')->clean();

        $this->Db()->query("UPDATE s_articles_top_seller_ro SET last_cleared = '2010-01-01'");

        Shopware()->Modules()->Articles()->sGetArticleCharts(3);

        $topSeller = $this->getAllTopSeller(" WHERE last_cleared > '2010-01-01' ");
        $this->assertCount(50, $topSeller);
    }

    public function testTopSellerCronJobRefresh()
    {
        $this->resetTopSeller();
        $this->TopSeller()->initTopSeller();

        $this->saveConfig('topSellerRefreshStrategy', 2);
        Shopware()->Container()->get('cache')->clean();

        $this->Db()->query("UPDATE s_articles_top_seller_ro SET last_cleared = '2010-01-01'");

        $result = $this->dispatch('/genusswelten/?p=1');
        $this->assertEquals(200, $result->getHttpResponseCode());

        $topSeller = $this->getAllTopSeller(" WHERE last_cleared > '2010-01-01' ");
        $this->assertCount(0, $topSeller, 'Topseller wurde durch dispatch aktualisiert');

        $cron = $this->Db()->fetchRow("SELECT * FROM s_crontab WHERE action = 'RefreshTopSeller'");
        $this->assertNotEmpty($cron);

        //the cron plugin isn't installed, so we can't use a dispatch on /backend/cron
        $this->Plugin()->refreshTopSeller();

        $topSeller = $this->getAllTopSeller(" WHERE last_cleared > '2010-01-01' ");
        $this->assertCount(
            count($this->getAllTopSeller()),
            $topSeller
        );
    }

    public function testTopSellerManualRefresh()
    {
        $this->resetTopSeller();
        $this->TopSeller()->initTopSeller();

        $this->saveConfig('topSellerRefreshStrategy', 1);
        Shopware()->Container()->get('cache')->clean();

        $this->Db()->query("UPDATE s_articles_top_seller_ro SET last_cleared = '2010-01-01'");

        $result = $this->dispatch('/genusswelten/?p=1');
        $this->assertEquals(200, $result->getHttpResponseCode());

        $topSeller = $this->getAllTopSeller(" WHERE last_cleared > '2010-01-01' ");
        $this->assertCount(0, $topSeller);

        //the cron plugin isn't installed, so we can't use a dispatch on /backend/cron
        $this->Plugin()->refreshTopSeller();

        $topSeller = $this->getAllTopSeller(" WHERE last_cleared > '2010-01-01' ");
        $this->assertCount(0, $topSeller);
    }

    protected function resetTopSeller($condition = '')
    {
        $this->Db()->query('DELETE FROM s_articles_top_seller_ro ' . $condition);
    }

    protected function getAllTopSeller($condition = '')
    {
        return $this->Db()->fetchAll('SELECT * FROM s_articles_top_seller_ro ' . $condition);
    }
}
