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

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class Shopware_Tests_Controllers_Backend_VoteTest extends Enlight_Components_Test_Controller_TestCase
{
    /**
     * Standard set up for every test - just disable auth
     */
    public function setUp()
    {
        parent::setUp();

        // disable auth and acl
        Shopware()->Container()->get('shopware.subscriber.auth')->setNoAuth();
        Shopware()->Container()->get('shopware.subscriber.auth')->setNoAcl();
    }

    /**
     * Test method to test the getVotesAction-method, which gets all article-votes
     *
     * @return array Contains the article, which is created in this method
     */
    public function testGetVotes()
    {
        $sql = 'DELETE FROM s_articles_vote';
        Shopware()->Db()->query($sql, []);
        $sql = "INSERT INTO s_articles_vote (`articleID`, `name`, `headline`, `comment`, `points`, `datum`, `active`, `email`, `answer`, `answer_date`)
                VALUES ('3', 'Patrick', 'Super!', 'Gutes Produkt!', '4', '2012-03-04 16:30:43', '1', 'test@example.com', '', '')";
        Shopware()->Db()->query($sql, []);

        $sql = "SELECT * FROM s_articles_vote WHERE articleID = 3 AND name='Patrick'";
        $data = Shopware()->Db()->fetchRow($sql, []);

        /* @var Enlight_Controller_Response_ResponseTestCase */
        $this->dispatch('backend/vote/list');
        $this->assertTrue($this->View()->success);

        $this->assertNotNull($this->View()->data);
        $this->assertNotNull($this->View()->total);

        //Testing the search-function
        $filter = ['filter' => Zend_Json::encode([['value' => 'test']])];
        $this->Request()->setMethod('POST')->setPost($filter);
        $this->dispatch('backend/premium/getPremiumArticles');

        $this->assertTrue($this->View()->success);
        $this->assertNotNull($this->View()->data);
        $this->assertNotNull($this->View()->total);

        return $data;
    }

    /**
     * Test method to test the answerVoteAction-method, which sets an answer to a vote
     *
     * @depends testGetVotes
     *
     * @param $data Contains the article, which is created in testGetVotes
     */
    public function testAnswerVote($data)
    {
        $data['answer'] = 'Test';
        $this->Request()->setMethod('POST')->setPost($data);

        $this->dispatch('backend/vote/update');

        $this->assertTrue($this->View()->success);
        $this->assertNotNull($this->View()->data);
        $this->assertNotNull($this->View()->data['answer_date']);
    }

    /**
     * Test method to test the acceptVoteAction-method, which sets the active-property to 1, so the vote is enabled
     * in the frontend
     *
     * @depends testGetVotes
     *
     * @param $data Contains the article, which is created in testGetVotes
     */
    public function testAcceptVote($data)
    {
        $sql = 'UPDATE s_articles_vote SET active=0 WHERE id=?';
        Shopware()->Db()->query($sql, [$data['id']]);

        $data['active'] = 1;

        $this->Request()->setMethod('POST')->setPost($data);

        $this->dispatch('backend/vote/update');

        $this->assertTrue($this->View()->success);
        $this->assertNotNull($this->View()->data);
    }

    /**
     * Test method to test the deleteVoteAction-method, which deletes the article created in the testGetVotes-method
     *
     * @depends testGetVotes
     *
     * @param $data Contains the article, which is created in testGetVotes
     */
    public function testDeleteVote($data)
    {
        $this->Request()->setMethod('POST')->setPost($data);
        $this->dispatch('backend/vote/delete');
        $this->assertTrue($this->View()->success);
    }
}
