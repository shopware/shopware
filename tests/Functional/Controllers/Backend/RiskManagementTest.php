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
class Shopware_Tests_Controllers_Backend_RiskManagementTest extends Enlight_Components_Test_Controller_TestCase
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
     * Tests the getPremiumArticlesAction()
     * to test if reading the articles is working
     * Additionally this method tests the search-function
     */
    public function testGetPayments()
    {
        /* @var Enlight_Controller_Response_ResponseTestCase */
        $this->dispatch('backend/risk_management/getPayments');
        $this->assertTrue($this->View()->success);

        $jsonBody = $this->View()->getAssign();

        $this->assertArrayHasKey('total', $jsonBody);
        $this->assertArrayHasKey('data', $jsonBody);
        $this->assertArrayHasKey('success', $jsonBody);
    }

    /**
     * This test tests the creating of a new premium-article.
     * The response has to contain the id of the created article.
     * This function is called before testEditPremiumArticle and testDeletePremiumArticle
     *
     * @return mixed
     */
    public function testCreateRule()
    {
        $manager = Shopware()->Models();
        /**
         * @var Shopware\Models\Payment\RuleSet
         */
        $repository = Shopware()->Models()->getRepository('Shopware\Models\Payment\RuleSet');

        $rules = $repository->findBy(['paymentId' => 2]);
        foreach ($rules as $rule) {
            $manager->remove($rule);
        }

        $manager->flush();

        $this->Request()->setMethod('POST')->setPost(
            [
                'paymentId' => 2,
                'rule1' => 'CUSTOMERGROUPISNOT',
                'rule2' => '',
                'value1' => '5',
                'value2' => '',
            ]
        );

        $this->dispatch('backend/risk_management/createRule');
        $this->assertTrue($this->View()->success);

        $jsonBody = $this->View()->getAssign();

        $this->assertArrayHasKey('data', $jsonBody);
        $this->assertArrayHasKey('success', $jsonBody);
        $this->assertArrayHasKey('id', $jsonBody['data']);

        return $jsonBody['data']['id'];
    }

    /**
     * This test method tests the editing of
     * a premium-article.
     * The testCreatePremiumArticle method is called before.
     *
     * @param $lastId The id of the last created article
     * @depends testCreateRule
     */
    public function testEditRule($lastId)
    {
        $this->Request()->setMethod('POST')->setPost(
            [
                'id' => $lastId,
                'paymentId' => 2,
                'rule1' => 'CUSTOMERGROUPISNOT',
                'rule2' => '',
                'value1' => '8',
                'value2' => '',
            ]
        );

        $this->dispatch('backend/risk_management/editRule');

        $jsonBody = $this->View()->getAssign();

        $this->assertArrayHasKey('data', $jsonBody);
        $this->assertArrayHasKey('success', $jsonBody);
    }

    /**
     * This test-method tests the deleting of a premium-article.
     *
     * @depends testCreateRule
     *
     * @param $lastId
     */
    public function testDeleteRule($lastId)
    {
        $this->Request()->setMethod('POST')->setPost(['id' => $lastId]);

        $this->dispatch('backend/risk_management/deleteRule');

        $jsonBody = $this->View()->getAssign();

        $this->assertArrayHasKey('success', $jsonBody);
    }
}
