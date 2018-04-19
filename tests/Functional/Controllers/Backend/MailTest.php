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
class Shopware_Tests_Controllers_Backend_MailTest extends Enlight_Components_Test_Controller_TestCase
{
    /**
     * @var array
     */
    public $testData = [
        'name' => 'Testmail123',
        'fromMail' => 'Shopware Demoshop',
        'fromName' => 'info@example.com',
        'subject' => 'Test Email Subject',
        'content' => 'Plaintext Content Example',
        'contentHtml' => 'HTML Context Example',
        'isHtml' => true,
    ];

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

    public function testCreateMail()
    {
        $this->testData['name'] .= uniqid(rand());

        $this->Request()->setMethod('POST')->setPost($this->testData);
        $response = $this->dispatch('/backend/mail/createMail');
        $jsonBody = Zend_Json::decode($response->getBody());

        $this->assertArrayHasKey('data', $jsonBody);
        $this->assertArrayHasKey('success', $jsonBody);
        $this->assertTrue($jsonBody['success']);

        $result = $jsonBody['data'];

        $this->assertEquals($this->testData['name'], $result['name']);
        $this->assertEquals($this->testData['fromMail'], $result['fromMail']);
        $this->assertEquals($this->testData['fromName'], $result['fromName']);
        $this->assertEquals($this->testData['subject'], $result['subject']);
        $this->assertEquals($this->testData['contentHtml'], $result['contentHtml']);
        $this->assertEquals($this->testData['isHtml'], $result['isHtml']);

        $this->assertArrayHasKey('id', $result);

        return $result['id'];
    }

    /**
     * @depends testCreateMail
     */
    public function testGetSingleMail($id)
    {
        $this->Request()->setMethod('GET');

        $response = $this->dispatch('/backend/mail/getMails?&node=NaN&id=' . $id);
        $body = $response->getBody();
        $jsonBody = Zend_Json::decode($body);

        $this->assertArrayHasKey('data', $jsonBody);
        $this->assertArrayHasKey('success', $jsonBody);
        $this->assertTrue($jsonBody['success']);

        $result = $jsonBody['data'];

        $this->assertEquals($this->testData['fromMail'], $result['fromMail']);
        $this->assertEquals($this->testData['fromName'], $result['fromName']);
        $this->assertEquals($this->testData['subject'], $result['subject']);
        $this->assertEquals($this->testData['contentHtml'], $result['contentHtml']);
        $this->assertEquals($this->testData['isHtml'], $result['isHtml']);

        $this->assertArrayHasKey('id', $result);

        return $result['id'];
    }

    /**
     * @depends testGetSingleMail
     */
    public function testUpdateMail($id)
    {
        $updateTestData = [
            'subject' => 'foobar',
        ];

        $this->Request()->setMethod('POST')->setPost($updateTestData);
        $response = $this->dispatch('/backend/mail/updateMail?id=' . $id);
        $jsonBody = Zend_Json::decode($response->getBody());

        $this->assertArrayHasKey('data', $jsonBody);
        $this->assertArrayHasKey('success', $jsonBody);
        $this->assertTrue($jsonBody['success']);

        $result = $jsonBody['data'];

        $this->assertEquals($updateTestData['subject'], $result['subject']);

        $this->assertArrayHasKey('id', $result);

        return $result['id'];
    }

    /**
     * @depends testUpdateMail
     */
    public function testRemoveMail($id)
    {
        $response = $this->dispatch('/backend/mail/removeMail?id=' . $id);
        $jsonBody = Zend_Json::decode($response->getBody());

        $this->assertArrayHasKey('success', $jsonBody);
        $this->assertTrue($jsonBody['success']);
    }

    public function testGetAttachmentsShouldBeSuccessful()
    {
        $this->Request()->setMethod('GET');

        $response = $this->dispatch('/backend/mail/getAttachments');
        $jsonBody = Zend_Json::decode($response->getBody());

        $this->assertArrayHasKey('data', $jsonBody);
        $this->assertArrayHasKey('success', $jsonBody);
        $this->assertTrue($jsonBody['success']);
    }

    public function testGetMailsShouldBeSuccessful()
    {
        $this->Request()->setMethod('GET');

        $response = $this->dispatch('/backend/mail/getMails?&node=NaN');
        $jsonBody = Zend_Json::decode($response->getBody());

        $this->assertArrayHasKey('data', $jsonBody);
        $this->assertArrayHasKey('success', $jsonBody);
        $this->assertTrue($jsonBody['success']);
    }
}
