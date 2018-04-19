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
class SimilarShownTest extends AbstractMarketing
{
    public function testResetSimilarShown()
    {
        $this->SimilarShown()->resetSimilarShown();
        $this->assertCount(0, $this->getAllSimilarShown());
    }

    public function testInitSimilarShown()
    {
        $this->insertDemoData();

        $this->SimilarShown()->initSimilarShown();

        $data = $this->getAllSimilarShown();

        $this->assertCount(144, $data);
    }

    public function testUpdateElapsedSimilarShownArticles()
    {
        $this->insertDemoData();

        $this->setSimilarShownInvalid();

        $this->SimilarShown()->updateElapsedSimilarShownArticles(10);

        $articles = $this->getAllSimilarShown(" WHERE init_date > '2010-01-01' ");

        $this->assertCount(10, $articles);

        $this->SimilarShown()->updateElapsedSimilarShownArticles();

        $articles = $this->getAllSimilarShown(" WHERE init_date > '2010-01-01' ");

        $this->assertCount(
            count($this->getAllSimilarShown()),
            $articles
        );
    }

    public function testRefreshSimilarShown()
    {
        $this->insertDemoData();
        $this->SimilarShown()->initSimilarShown();

        $similarShown = $this->getAllSimilarShown();

        foreach ($similarShown as $combination) {
            $this->SimilarShown()->refreshSimilarShown($combination['article_id'], $combination['related_article_id']);
            $updated = $this->getAllSimilarShown(
                ' WHERE article_id = ' . $combination['article_id'] .
                ' AND related_article_id = ' . $combination['related_article_id']
            );
            $updated = $updated[0];
            $this->assertEquals($combination['viewed'] + 1, $updated['viewed']);
        }
    }

    /**
     * @group skipElasticSearch
     */
    public function testSimilarShownLiveRefresh()
    {
        $this->insertDemoData();
        $this->SimilarShown()->initSimilarShown();

        $countBefore = count($this->getAllSimilarShown());
        $this->saveConfig('similarRefreshStrategy', 3);
        Shopware()->Container()->get('cache')->clean();

        $this->setSimilarShownInvalid('2010-01-01', 'LIMIT 20');

        Shopware()->Events()->notify('Shopware_Plugins_LastArticles_ResetLastArticles', []);

        $articles = $this->getAllSimilarShown();

        $this->assertCount($countBefore - 20, $articles);
    }

    public function testSimilarCronJobRefresh()
    {
        $this->insertDemoData();
        $this->SimilarShown()->initSimilarShown();

        $this->saveConfig('similarRefreshStrategy', 2);
        Shopware()->Container()->get('cache')->clean();

        $this->setSimilarShownInvalid();

        $result = $this->dispatch('/sommerwelten/accessoires/170/sonnenbrille-red');
        $this->assertEquals(200, $result->getHttpResponseCode());

        $articles = $this->getAllSimilarShown(" WHERE init_date > '2010-01-01' ");
        $this->assertCount(0, $articles);

        $cron = $this->Db()->fetchRow("SELECT * FROM s_crontab WHERE action = 'RefreshSimilarShown'");
        $this->assertNotEmpty($cron);

        //the cron plugin isn't installed, so we can't use a dispatch on /backend/cron
        $this->Plugin()->refreshSimilarShown(new \Enlight_Event_EventArgs(['subject' => $this]));

        $articles = $this->getAllSimilarShown(" WHERE init_date > '2010-01-01' ");
        $this->assertCount(
            count($this->getAllSimilarShown()),
            $articles
        );
    }

    protected function getDemoData()
    {
        return require __DIR__ . '/fixtures/similarShown.php';
    }

    /**
     * The demo data contains 144 combinations of the similar shown articles for three users.
     */
    protected function insertDemoData()
    {
        $this->Db()->query('DELETE FROM s_emarketing_lastarticles');
        $statement = $this->Db()->prepare('
            INSERT INTO s_emarketing_lastarticles (articleID, sessionID, time, userID, shopID)
            VALUES(:articleID, :sessionID, :time, :userID, :shopID)'
        );
        foreach ($this->getDemoData() as $data) {
            $statement->execute($data);
        }
    }

    protected function getAllSimilarShown($condition = '')
    {
        return $this->Db()->fetchAll('SELECT * FROM s_articles_similar_shown_ro ' . $condition);
    }

    protected function resetSimilarShown($condition = '')
    {
        $this->Db()->query('DELETE FROM s_articles_similar_shown_ro ' . $condition);
    }

    protected function setSimilarShownInvalid($date = '2010-01-01', $condition = '')
    {
        $this->Db()->query(' UPDATE s_articles_similar_shown_ro SET init_date = :date ' . $condition, [
            'date' => $date,
        ]);
    }
}
