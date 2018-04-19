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

class sAdminTest extends PHPUnit\Framework\TestCase
{
    /**
     * @var sAdmin
     */
    private $module;

    /**
     * @var sBasket
     */
    private $basketModule;

    /**
     * @var sSystem
     */
    private $systemModule;

    /**
     * @var Shopware_Components_Config
     */
    private $config;

    /**
     * @var Enlight_Components_Session_Namespace The session data
     */
    private $session;

    /**
     * @var Shopware_Components_Snippet_Manager Snippet manager
     */
    private $snippetManager;

    /**
     * @var Enlight_Controller_Front
     */
    private $front;

    public function setUp()
    {
        parent::setUp();

        Shopware()->Container()->get('models')->clear();
        Shopware()->Front()->setRequest(new Enlight_Controller_Request_RequestHttp());

        $this->module = Shopware()->Modules()->Admin();
        $this->config = Shopware()->Config();
        $this->session = Shopware()->Session();
        $this->front = Shopware()->Front();
        $this->snippetManager = Shopware()->Snippets();
        $this->basketModule = Shopware()->Modules()->Basket();
        $this->systemModule = Shopware()->System();
        $this->systemModule->sCurrency = Shopware()->Db()->fetchRow('SELECT * FROM s_core_currencies WHERE currency LIKE "EUR"');
        $this->systemModule->sSESSION_ID = null;
        $this->session->offsetSet('sessionId', null);
    }

    protected function tearDown()
    {
        parent::tearDown();
        Shopware()->Container()->get('models')->clear();
    }

    /**
     * @covers \sAdmin::sGetPaymentMeanById
     */
    public function testsGetPaymentMeanById()
    {
        // Fetching non-existing payment means returns null
        $this->assertEmpty($this->module->sGetPaymentMeanById(0));

        // Fetching existing inactive payment means returns the data array
        $sepaData = $this->module->sGetPaymentMeanById(6);
        $this->assertInternalType('array', $sepaData);
        $this->assertArrayHasKey('id', $sepaData);
        $this->assertArrayHasKey('name', $sepaData);
        $this->assertArrayHasKey('description', $sepaData);
        $this->assertArrayHasKey('debit_percent', $sepaData);
        $this->assertArrayHasKey('surcharge', $sepaData);
        $this->assertArrayHasKey('surchargestring', $sepaData);
        $this->assertArrayHasKey('active', $sepaData);
        $this->assertArrayHasKey('esdactive', $sepaData);

        // Fetching existing active payment means returns the data array
        $debitData = $this->module->sGetPaymentMeanById(2);
        $this->assertInternalType('array', $debitData);
        $this->assertArrayHasKey('id', $debitData);
        $this->assertArrayHasKey('name', $debitData);
        $this->assertArrayHasKey('description', $debitData);
        $this->assertArrayHasKey('debit_percent', $debitData);
        $this->assertArrayHasKey('surcharge', $debitData);
        $this->assertArrayHasKey('surchargestring', $debitData);
        $this->assertArrayHasKey('active', $debitData);
        $this->assertArrayHasKey('esdactive', $debitData);

        $customer = $this->createDummyCustomer();

        $this->assertEquals($this->config->get('defaultPayment'), $customer->getPaymentId());

        $this->deleteDummyCustomer($customer);
    }

    /**
     * @covers \sAdmin::sGetPaymentMeans
     */
    public function testsGetPaymentMeans()
    {
        $result = $this->module->sGetPaymentMeans();
        foreach ($result as $paymentMean) {
            $this->assertArrayHasKey('id', $paymentMean);
            $this->assertArrayHasKey('name', $paymentMean);
            $this->assertArrayHasKey('description', $paymentMean);
            $this->assertArrayHasKey('debit_percent', $paymentMean);
            $this->assertArrayHasKey('surcharge', $paymentMean);
            $this->assertArrayHasKey('surchargestring', $paymentMean);
            $this->assertArrayHasKey('active', $paymentMean);
            $this->assertArrayHasKey('esdactive', $paymentMean);
            $this->assertContains($paymentMean['id'], [3, 5, 6]);
        }
    }

    /**
     * @covers \sAdmin::sInitiatePaymentClass
     */
    public function testsInitiatePaymentClass()
    {
        $payments = Shopware()->Models()->getRepository('Shopware\Models\Payment\Payment')->findAll();

        foreach ($payments as $payment) {
            $paymentClass = $this->module->sInitiatePaymentClass($this->module->sGetPaymentMeanById($payment->getId()));
            if (is_bool($paymentClass)) {
                $this->assertFalse($paymentClass);
            } else {
                $this->assertInstanceOf('ShopwarePlugin\PaymentMethods\Components\BasePaymentMethod', $paymentClass);
                Shopware()->Front()->setRequest(new Enlight_Controller_Request_RequestHttp());

                $requestData = Shopware()->Front()->Request()->getParams();
                $validationResult = $paymentClass->validate($requestData);
                $this->assertTrue(is_array($validationResult));
                if (count($validationResult)) {
                    $this->assertArrayHasKey('sErrorFlag', $validationResult);
                    $this->assertArrayHasKey('sErrorMessages', $validationResult);
                }
            }
        }
    }

    /**
     * @covers \sAdmin::sValidateStep3
     * @expectedException \Enlight_Exception
     * @expectedExceptionMessage sValidateStep3 #00: No payment id
     */
    public function testExceptionInsValidateStep3()
    {
        $this->module->sValidateStep3();
    }

    /**
     * @covers \sAdmin::sValidateStep3
     */
    public function testsValidateStep3()
    {
        $this->front->Request()->setPost('sPayment', 2);

        $result = $this->module->sValidateStep3();
        $this->assertArrayHasKey('checkPayment', $result);
        $this->assertArrayHasKey('paymentData', $result);
        $this->assertArrayHasKey('sProcessed', $result);
        $this->assertArrayHasKey('sPaymentObject', $result);

        $this->assertInternalType('array', $result['checkPayment']);
        $this->assertCount(2, $result['checkPayment']);
        $this->assertInternalType('array', $result['paymentData']);
        $this->assertCount(21, $result['paymentData']);
        $this->assertInternalType('boolean', $result['sProcessed']);
        $this->assertTrue($result['sProcessed']);
        $this->assertInternalType('object', $result['sPaymentObject']);
        $this->assertInstanceOf('ShopwarePlugin\PaymentMethods\Components\BasePaymentMethod', $result['sPaymentObject']);
    }

    /**
     * @covers \sAdmin::sUpdateNewsletter
     */
    public function testsUpdateNewsletter()
    {
        $email = uniqid(rand()) . 'test@foobar.com';

        // Test insertion
        $this->assertTrue($this->module->sUpdateNewsletter(true, $email));
        $newsletterSubscription = Shopware()->Db()->fetchRow(
            'SELECT * FROM s_campaigns_mailaddresses WHERE email = ?',
            [$email]
        );
        $this->assertNotNull($newsletterSubscription);
        $this->assertEquals(0, $newsletterSubscription['customer']);
        $this->assertEquals(1, $newsletterSubscription['groupID']);

        // Test removal
        $this->assertTrue($this->module->sUpdateNewsletter(false, $email));
        $newsletterSubscription = Shopware()->Db()->fetchRow(
            'SELECT * FROM s_campaigns_mailaddresses WHERE email = ?',
            [$email]
        );
        $this->assertFalse($newsletterSubscription);

        // Retest insertion for customers
        $this->assertTrue($this->module->sUpdateNewsletter(true, $email, true));
        $newsletterSubscription = Shopware()->Db()->fetchRow(
            'SELECT * FROM s_campaigns_mailaddresses WHERE email = ?',
            [$email]
        );
        $this->assertNotNull($newsletterSubscription);
        $this->assertEquals(1, $newsletterSubscription['customer']);
        $this->assertEquals(0, $newsletterSubscription['groupID']);

        // Test removal
        $this->assertTrue($this->module->sUpdateNewsletter(false, $email));
        $newsletterSubscription = Shopware()->Db()->fetchRow(
            'SELECT * FROM s_campaigns_mailaddresses WHERE email = ?',
            [$email]
        );
        $this->assertFalse($newsletterSubscription);
    }

    /**
     * @covers \sAdmin::sUpdatePayment
     */
    public function testsUpdatePayment()
    {
        // Test no user id
        $this->assertFalse($this->module->sUpdatePayment());

        $customer = $this->createDummyCustomer();
        $this->session->offsetSet('sUserId', $customer->getId());

        // Test that operation succeeds even without payment id
        $this->assertTrue($this->module->sUpdatePayment());
        $this->assertEquals(
            0,
            Shopware()->Db()->fetchOne('SELECT paymentID FROM s_user WHERE id = ?', [$customer->getId()])
        );

        // Setup dummy test data and test with it
        $this->front->Request()->setPost([
            'sPayment' => 2,
        ]);
        $this->assertTrue($this->module->sUpdatePayment());
        $this->assertEquals(
            2,
            Shopware()->Db()->fetchOne('SELECT paymentID FROM s_user WHERE id = ?', [$customer->getId()])
        );

        $this->deleteDummyCustomer($customer);
    }

    /**
     * @covers \sAdmin::sLogin
     */
    public function testsLogin()
    {
        // Test with no data, get error
        $result = $this->module->sLogin();
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('sErrorFlag', $result);
        $this->assertArrayHasKey('sErrorMessages', $result);
        $this->assertCount(1, $result['sErrorMessages']);
        $this->assertContains(
            $this->snippetManager->getNamespace('frontend/account/internalMessages')
                ->get('LoginFailure', 'Wrong email or password'),
            $result['sErrorMessages']
        );
        $this->assertCount(2, $result['sErrorFlag']);
        $this->assertArrayHasKey('email', $result['sErrorFlag']);
        $this->assertArrayHasKey('password', $result['sErrorFlag']);

        // Test with wrong data, get error
        $this->front->Request()->setPost([
            'email' => uniqid(rand()) . 'test',
            'password' => uniqid(rand()) . 'test',
        ]);
        $result = $this->module->sLogin();
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('sErrorFlag', $result);
        $this->assertArrayHasKey('sErrorMessages', $result);
        $this->assertCount(1, $result['sErrorMessages']);
        $this->assertContains(
            $this->snippetManager->getNamespace('frontend/account/internalMessages')
                ->get('LoginFailure', 'Wrong email or password'),
            $result['sErrorMessages']
        );
        $this->assertNull($result['sErrorFlag']);

        $customer = $this->createDummyCustomer();

        // Test successful login
        $this->front->Request()->setPost([
            'email' => $customer->getEmail(),
            'password' => 'fooobar',
        ]);
        $result = $this->module->sLogin();
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('sErrorFlag', $result);
        $this->assertArrayHasKey('sErrorMessages', $result);
        $this->assertNull($result['sErrorFlag']);
        $this->assertNull($result['sErrorMessages']);

        // Test wrong pre-hashed password. Need a user with md5 encoded password
        Shopware()->Db()->update(
            's_user',
            [
                'password' => md5('fooobar'),
                'encoder' => 'md5',
            ],
            'id = ' . $customer->getId()
        );

        $this->front->Request()->setPost([
            'email' => $customer->getEmail(),
            'passwordMD5' => uniqid(rand()),
        ]);
        $result = $this->module->sLogin(true);
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('sErrorFlag', $result);
        $this->assertArrayHasKey('sErrorMessages', $result);
        $this->assertNull($result['sErrorFlag']);
        $this->assertCount(1, $result['sErrorMessages']);
        $this->assertContains(
            $this->snippetManager->getNamespace('frontend/account/internalMessages')
                ->get('LoginFailure', 'Wrong email or password'),
            $result['sErrorMessages']
        );

        // Test correct pre-hashed password
        $this->front->Request()->setPost([
            'email' => $customer->getEmail(),
            'passwordMD5' => md5('fooobar'),
        ]);
        $result = $this->module->sLogin(true);
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('sErrorFlag', $result);
        $this->assertArrayHasKey('sErrorMessages', $result);
        $this->assertNull($result['sErrorFlag']);
        $this->assertNull($result['sErrorMessages']);

        $modifiedMd5User = Shopware()->Db()->fetchRow(
            'SELECT * FROM s_user WHERE id = ?',
            [$customer->getId()]
        );

        // Test that it's the same user, but with different last login
        $this->assertEquals($modifiedMd5User['email'], $customer->getEmail());
        $this->assertEquals($modifiedMd5User['password'], md5('fooobar'));
        $this->assertNotEquals($modifiedMd5User['lastlogin'], $customer->getLastLogin()->format('Y-m-d H:i:s'));

        // Test inactive account
        Shopware()->Db()->update('s_user', ['active' => 0], 'id = ' . $customer->getId());
        $result = $this->module->sLogin(true);
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('sErrorFlag', $result);
        $this->assertArrayHasKey('sErrorMessages', $result);
        $this->assertNull($result['sErrorFlag']);
        $this->assertCount(1, $result['sErrorMessages']);
        $this->assertContains(
            $this->snippetManager->getNamespace('frontend/account/internalMessages')
                ->get(
                    'LoginFailureActive',
                    'Your account is disabled. Please contact us.'
                ),
            $result['sErrorMessages']
        );

        // Test brute force lockout
        Shopware()->Db()->update('s_user', ['active' => 1], 'id = ' . $customer->getId());
        $this->front->Request()->setPost([
            'email' => $customer->getEmail(),
            'password' => 'asasasasas',
        ]);
        $this->module->sLogin();
        $this->module->sLogin();
        $this->module->sLogin();
        $this->module->sLogin();
        $this->module->sLogin();
        $this->module->sLogin();
        $this->module->sLogin();
        $this->module->sLogin();
        $this->module->sLogin();
        $result = $this->module->sLogin();
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('sErrorFlag', $result);
        $this->assertArrayHasKey('sErrorMessages', $result);
        $this->assertNull($result['sErrorFlag']);
        $this->assertCount(1, $result['sErrorMessages']);
        $this->assertContains(
            $this->snippetManager->getNamespace('frontend/account/internalMessages')
                ->get(
                    'LoginFailureLocked',
                    'Too many failed logins. Your account was temporary deactivated.'
                ),
            $result['sErrorMessages']
        );

        $this->deleteDummyCustomer($customer);
    }

    /**
     * @covers \sAdmin::sCheckUser
     */
    public function testsCheckUser()
    {
        $customer = $this->createDummyCustomer();

        // Basic failing case
        $this->assertFalse($this->module->sCheckUser());

        // Test successful login
        $this->front->Request()->setPost([
            'email' => $customer->getEmail(),
            'password' => 'fooobar',
        ]);
        $result = $this->module->sLogin();
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('sErrorFlag', $result);
        $this->assertArrayHasKey('sErrorMessages', $result);
        $this->assertNull($result['sErrorFlag']);
        $this->assertNull($result['sErrorMessages']);

        // Test that user is correctly logged in
        $this->assertTrue($this->module->sCheckUser());

        // Force timeout
        Shopware()->Db()->update('s_user', ['lastlogin' => '2000-01-01 00:00:00'], 'id = ' . $customer->getId());
        $this->assertFalse($this->module->sCheckUser());

        $this->assertEquals($customer->getGroup()->getKey(), $this->session->offsetGet('sUserGroup'));
        $this->assertInternalType('array', $this->session->offsetGet('sUserGroupData'));
        $this->assertArrayHasKey('groupkey', $this->session->offsetGet('sUserGroupData'));
        $this->assertArrayHasKey('description', $this->session->offsetGet('sUserGroupData'));
        $this->assertArrayHasKey('tax', $this->session->offsetGet('sUserGroupData'));
        $this->assertArrayHasKey('taxinput', $this->session->offsetGet('sUserGroupData'));
        $this->assertArrayHasKey('mode', $this->session->offsetGet('sUserGroupData'));
        $this->assertArrayHasKey('discount', $this->session->offsetGet('sUserGroupData'));
        $this->assertArrayHasKey('minimumorder', $this->session->offsetGet('sUserGroupData'));
        $this->assertArrayHasKey('minimumordersurcharge', $this->session->offsetGet('sUserGroupData'));

        $this->deleteDummyCustomer($customer);
    }

    /**
     * @covers \sAdmin::sGetCountryList
     */
    public function testsGetCountryList()
    {
        // Test with default country data
        $result = $this->module->sGetCountryList();
        foreach ($result as $country) {
            $this->assertArrayHasKey('id', $country);
            $this->assertArrayHasKey('countryname', $country);
            $this->assertArrayHasKey('countryiso', $country);
            $this->assertArrayHasKey('areaID', $country);
            $this->assertArrayHasKey('countryen', $country);
            $this->assertArrayHasKey('shippingfree', $country);
            $this->assertArrayHasKey('taxfree', $country);
            $this->assertArrayHasKey('display_state_in_registration', $country);
            $this->assertArrayHasKey('force_state_in_registration', $country);
            $this->assertArrayHasKey('states', $country);
            $this->assertArrayHasKey('flag', $country);
        }

        // Add translations
        $existingCountryData = Shopware()->Db()->fetchRow("
            SELECT * FROM s_core_translations
            WHERE objecttype = 'config_countries' AND objectlanguage = 1
        ");
        $existingStateData = Shopware()->Db()->fetchRow("
            SELECT * FROM s_core_translations
            WHERE objecttype = 'config_country_states' AND objectlanguage = 1
        ");

        $demoCountryData = [
            'objectkey' => 1,
            'objectlanguage' => 1,
            'objecttype' => 'config_countries',
            'objectdata' => serialize(
                [
                    2 => [
                        'active' => '1',
                        'countryname' => 'Germany',
                    ],
                ]
            ),
        ];
        $demoStateData = [
            'objectkey' => 1,
            'objectlanguage' => 1,
            'objecttype' => 'config_country_states',
            'objectdata' => serialize(
                [
                    2 => [
                        'name' => '111',
                    ],
                    3 => [
                        'name' => '222',
                    ],
                ]
            ),
        ];

        if ($existingCountryData) {
            Shopware()->Db()->update('s_core_translations', $demoCountryData, 'id = ' . $existingCountryData['id']);
        } else {
            Shopware()->Db()->insert('s_core_translations', $demoCountryData);
        }
        if ($existingStateData) {
            Shopware()->Db()->update('s_core_translations', $demoStateData, 'id = ' . $existingStateData['id']);
        } else {
            Shopware()->Db()->insert('s_core_translations', $demoStateData);
        }

        // Test with translations but display_states = false
        $result = $this->module->sGetCountryList();
        $country = $result[0]; // Germany
        $this->assertArrayHasKey('id', $country);
        $this->assertArrayHasKey('countryname', $country);
        $this->assertArrayHasKey('countryiso', $country);
        $this->assertArrayHasKey('areaID', $country);
        $this->assertArrayHasKey('countryen', $country);
        $this->assertArrayHasKey('shippingfree', $country);
        $this->assertArrayHasKey('taxfree', $country);
        $this->assertArrayHasKey('display_state_in_registration', $country);
        $this->assertArrayHasKey('force_state_in_registration', $country);
        $this->assertArrayHasKey('states', $country);
        $this->assertArrayHasKey('flag', $country);
        $this->assertCount(0, $country['states']);
        $this->assertEquals('Germany', $country['countryname']);

        // Hack the current system shop, so we can properly test this
        Shopware()->Shop()->setDefault(false);

        // Make Germany display states, so we can test it
        $existingGermanyData = Shopware()->Db()->fetchRow("
            SELECT * FROM s_core_countries
            WHERE countryiso = 'DE'
        ");
        Shopware()->Db()->update(
            's_core_countries',
            ['display_state_in_registration' => 1],
            'id = ' . $existingGermanyData['id']
        );

        // Test with translations and states
        $result = $this->module->sGetCountryList();
        $country = $result[0]; // Germany
        $this->assertArrayHasKey('id', $country);
        $this->assertArrayHasKey('countryname', $country);
        $this->assertArrayHasKey('countryiso', $country);
        $this->assertArrayHasKey('areaID', $country);
        $this->assertArrayHasKey('countryen', $country);
        $this->assertArrayHasKey('shippingfree', $country);
        $this->assertArrayHasKey('taxfree', $country);
        $this->assertArrayHasKey('display_state_in_registration', $country);
        $this->assertArrayHasKey('force_state_in_registration', $country);
        $this->assertArrayHasKey('states', $country);
        $this->assertArrayHasKey('flag', $country);
        $this->assertCount(16, $country['states']);
        $this->assertEquals('Germany', $country['countryname']);
        foreach ($country['states'] as $state) {
            $this->assertArrayHasKey('id', $state);
            $this->assertArrayHasKey('countryID', $state);
            $this->assertArrayHasKey('name', $state);
            $this->assertArrayHasKey('shortcode', $state);
            $this->assertArrayHasKey('active', $state);
        }
        $this->assertContains('111', array_column($country['states'], 'name'));

        // If backup data exists, restore it
        if ($existingCountryData) {
            $existingCountryDataId = $existingCountryData['id'];
            unset($existingCountryData['id']);
            Shopware()->Db()->update('s_core_translations', $existingCountryData, 'id = ' . $existingCountryDataId);
        }
        if ($existingStateData) {
            $existingStateDataId = $existingStateData['id'];
            unset($existingStateData['id']);
            Shopware()->Db()->update('s_core_translations', $existingStateData, 'id = ' . $existingStateDataId);
        }
        if ($existingGermanyData) {
            $existingGermanyDataId = $existingGermanyData['id'];
            unset($existingGermanyData['id']);
            Shopware()->Db()->update('s_core_countries', $existingGermanyData, 'id = ' . $existingGermanyDataId);
        }

        // Remove shop hack
        Shopware()->Shop()->setDefault(true);
    }

    /**
     * @covers \sAdmin::sGetDownloads
     */
    public function testsGetDownloads()
    {
        $customer = $this->createDummyCustomer();
        $this->session->offsetSet('sUserId', $customer->getId());

        // New customers don't have available downloads
        $downloads = $this->module->sGetDownloads();
        $this->assertCount(0, $downloads['orderData']);

        // Inject demo data
        $orderData = [
            'ordernumber' => uniqid(rand()),
            'userID' => $customer->getId(),
            'invoice_amount' => '37.99',
            'invoice_amount_net' => '31.92',
            'invoice_shipping' => '0',
            'invoice_shipping_net' => '0',
            'ordertime' => '2014-03-14 10:26:20',
            'status' => '0',
            'cleared' => '17',
            'paymentID' => '4',
            'transactionID' => '',
            'comment' => '',
            'customercomment' => '',
            'internalcomment' => '',
            'net' => '0',
            'taxfree' => '0',
            'partnerID' => '',
            'temporaryID' => '',
            'referer' => '',
            'cleareddate' => null,
            'trackingcode' => '',
            'language' => '2',
            'dispatchID' => '9',
            'currency' => 'EUR',
            'currencyFactor' => '1',
            'subshopID' => '1',
            'remote_addr' => '127.0.0.1',
        ];

        Shopware()->Db()->insert('s_order', $orderData);
        $orderId = Shopware()->Db()->lastInsertId();

        $orderDetailsData = [
            'orderID' => $orderId,
            'ordernumber' => '20003',
            'articleID' => '98765',
            'articleordernumber' => 'SW10196',
            'price' => '34.99',
            'quantity' => '1',
            'name' => 'ESD download article',
            'status' => '0',
            'shipped' => '0',
            'shippedgroup' => '0',
            'releasedate' => '0000-00-00',
            'modus' => '0',
            'esdarticle' => '1',
            'taxID' => '1',
            'tax_rate' => '19',
            'config' => '',
        ];

        Shopware()->Db()->insert('s_order_details', $orderDetailsData);
        $orderDetailId = Shopware()->Db()->lastInsertId();

        $orderEsdData = [
            'serialID' => '8',
            'esdID' => '2',
            'userID' => $customer->getId(),
            'orderID' => $orderId,
            'orderdetailsID' => $orderDetailId,
            'datum' => '2014-03-14 10:26:20',
        ];

        Shopware()->Db()->insert('s_order_esd', $orderEsdData);

        // Mock a login
        $orderEsdId = Shopware()->Db()->lastInsertId();

        // Calling the method should now return the expected data
        $downloads = $this->module->sGetDownloads();
        $result = $downloads['orderData'];

        $this->assertCount(1, $result);
        $esd = end($result);
        $this->assertArrayHasKey('id', $esd);
        $this->assertArrayHasKey('ordernumber', $esd);
        $this->assertArrayHasKey('invoice_amount', $esd);
        $this->assertArrayHasKey('invoice_amount_net', $esd);
        $this->assertArrayHasKey('invoice_shipping', $esd);
        $this->assertArrayHasKey('invoice_shipping_net', $esd);
        $this->assertArrayHasKey('datum', $esd);
        $this->assertArrayHasKey('status', $esd);
        $this->assertArrayHasKey('cleared', $esd);
        $this->assertArrayHasKey('comment', $esd);
        $this->assertArrayHasKey('details', $esd);
        $this->assertEquals($orderData['ordernumber'], $esd['ordernumber']);
        $this->assertEquals('37,99', $esd['invoice_amount']);
        $this->assertEquals($orderData['invoice_amount_net'], $esd['invoice_amount_net']);
        $this->assertEquals($orderData['invoice_shipping'], $esd['invoice_shipping']);
        $this->assertEquals($orderData['invoice_shipping_net'], $esd['invoice_shipping_net']);
        $this->assertEquals('14.03.2014 10:26', $esd['datum']);
        $this->assertEquals($orderData['status'], $esd['status']);
        $this->assertEquals($orderData['cleared'], $esd['cleared']);
        $this->assertEquals($orderData['comment'], $esd['comment']);
        $this->assertCount(1, $esd['details']);
        $esdDetail = end($esd['details']);

        $this->assertArrayHasKey('id', $esdDetail);
        $this->assertArrayHasKey('orderID', $esdDetail);
        $this->assertArrayHasKey('ordernumber', $esdDetail);
        $this->assertArrayHasKey('articleID', $esdDetail);
        $this->assertArrayHasKey('articleordernumber', $esdDetail);
        $this->assertArrayHasKey('serial', $esdDetail);
        $this->assertArrayHasKey('esdLink', $esdDetail);
        $this->assertNotNull($esdDetail['esdLink']);

        return [
            'customer' => $customer,
            'orderEsdId' => $orderEsdId,
            'orderDetailId' => $orderDetailId,
            'orderId' => $orderId,
            'orderData' => $orderData,
        ];
    }

    /**
     * @covers \sAdmin::sGetOpenOrderData
     * @depends testsGetDownloads
     * @ticket SW-5653
     */
    public function testsGetOpenOrderData($demoData)
    {
        // Inherit data from previous test
        $customer = $demoData['customer'];
        $oldOrderId = $demoData['orderId'];
        $orderEsdId = $demoData['orderEsdId'];
        $orderNumber = uniqid(rand());

        // Add another order to the customer
        $orderData = [
            'ordernumber' => $orderNumber,
            'userID' => $customer->getId(),
            'invoice_amount' => '16.89',
            'invoice_amount_net' => '14.2',
            'invoice_shipping' => '3.9',
            'invoice_shipping_net' => '3.28',
            'ordertime' => '2013-04-08 17:39:30',
            'status' => '0',
            'cleared' => '17',
            'paymentID' => '5',
            'transactionID' => '',
            'comment' => '',
            'customercomment' => '',
            'internalcomment' => '',
            'net' => '0',
            'taxfree' => '0',
            'partnerID' => '',
            'temporaryID' => '',
            'referer' => '',
            'cleareddate' => null,
            'trackingcode' => '',
            'language' => '2',
            'dispatchID' => '9',
            'currency' => 'EUR',
            'currencyFactor' => '1',
            'subshopID' => '1',
            'remote_addr' => '172.16.10.71',
        ];

        Shopware()->Db()->insert('s_order', $orderData);
        $orderId = Shopware()->Db()->lastInsertId();

        Shopware()->Db()->query("
            INSERT IGNORE INTO `s_order_details` (`orderID`, `ordernumber`, `articleID`, `articleordernumber`, `price`, `quantity`, `name`, `status`, `shipped`, `shippedgroup`, `releasedate`, `modus`, `esdarticle`, `taxID`, `tax_rate`, `config`) VALUES
            (?, ?, 12, 'SW10012', 9.99, 1, 'Kobra Vodka 37,5%', 0, 0, 0, '0000-00-00', 0, 0, 1, 19, ''),
            (?, ?, 0, 'SHIPPINGDISCOUNT', -2, 1, 'Warenkorbrabatt', 0, 0, 0, '0000-00-00', 4, 0, 0, 19, ''),
            (?, ?, 0, 'sw-surcharge', 5, 1, 'Mindermengenzuschlag', 0, 0, 0, '0000-00-00', 4, 0, 0, 19, '');
        ", [
            $orderId, $orderNumber,
            $orderId, $orderNumber,
            $orderId, $orderNumber,
        ]);

        // At this point, the user is not logged in so we should have no data
        $data = $this->module->sGetOpenOrderData();
        $this->assertCount(0, $data['orderData']);

        // Mock a login
        $this->session->offsetSet('sUserId', $customer->getId());

        // Calling the method should now return the expected data
        $result = $this->module->sGetOpenOrderData();
        $result = $result['orderData'];

        $this->assertCount(2, $result);
        foreach ($result as $order) {
            $this->assertArrayHasKey('id', $order);
            $this->assertArrayHasKey('ordernumber', $order);
            $this->assertArrayHasKey('invoice_amount', $order);
            $this->assertArrayHasKey('invoice_amount_net', $order);
            $this->assertArrayHasKey('invoice_shipping', $order);
            $this->assertArrayHasKey('invoice_shipping_net', $order);
            $this->assertArrayHasKey('datum', $order);
            $this->assertArrayHasKey('status', $order);
            $this->assertArrayHasKey('cleared', $order);
            $this->assertArrayHasKey('comment', $order);
            $this->assertArrayHasKey('details', $order);
            foreach ($order['details'] as $detail) {
                $this->assertArrayHasKey('id', $detail);
                $this->assertArrayHasKey('orderID', $detail);
                $this->assertArrayHasKey('ordernumber', $detail);
                $this->assertArrayHasKey('articleID', $detail);
                $this->assertArrayHasKey('articleordernumber', $detail);
            }

            // This tests SW-5653
            if ($order['id'] == $orderId) {
                $this->assertNotEmpty($order);
                $this->assertEquals($orderNumber, $order['ordernumber']);
                $this->assertEquals($customer->getId(), $order['userID']);
                break;
            }
        }

        Shopware()->Db()->delete('s_order_esd', 'id = ' . $orderEsdId);
        Shopware()->Db()->delete('s_order_details', 'orderID = ' . $orderId);
        Shopware()->Db()->delete('s_order_details', 'orderID = ' . $oldOrderId);
        Shopware()->Db()->delete('s_order', 'id = ' . $orderId);
        Shopware()->Db()->delete('s_order', 'id = ' . $oldOrderId);
        $this->deleteDummyCustomer($customer);
    }

    /**
     * @covers \sAdmin::sGetUserMailById
     * @covers \sAdmin::sGetUserByMail
     * @covers \sAdmin::sGetUserNameById
     */
    public function testGetEmailAndUser()
    {
        $customer = $this->createDummyCustomer();

        // Test sGetUserMailById with null and expected cases
        $this->assertNull($this->module->sGetUserMailById());
        $this->session->offsetSet('sUserId', $customer->getId());
        $this->assertEquals($customer->getEmail(), $this->module->sGetUserMailById());

        // Test sGetUserByMail with null and expected cases
        $this->assertNull($this->module->sGetUserByMail(uniqid(rand())));
        $this->assertEquals($customer->getId(), $this->module->sGetUserByMail($customer->getEmail()));

        // Test sGetUserNameById with null and expected cases
        $this->assertEmpty($this->module->sGetUserNameById(uniqid(rand())));
        $this->assertEquals(
            ['firstname' => 'Max', 'lastname' => 'Mustermann'],
            $this->module->sGetUserNameById($customer->getId())
        );

        $this->deleteDummyCustomer($customer);
    }

    /**
     * @covers \sAdmin::sGetUserData
     */
    public function testsGetUserDataWithoutLogin()
    {
        $this->assertEquals(
            ['additional' => [
                    'country' => [],
                    'countryShipping' => [],
                    'stateShipping' => ['id' => 0],
                ],
            ],
            $this->module->sGetUserData()
        );

        $this->session->offsetSet('sCountry', 20);

        $this->assertEquals(
            ['additional' => [
                    'country' => [
                        'id' => '20',
                        'countryname' => 'Namibia',
                        'countryiso' => 'NA',
                        'areaID' => '2',
                        'countryen' => 'NAMIBIA',
                        'position' => '10',
                        'notice' => '',
                        'shippingfree' => '0',
                        'taxfree' => '0',
                        'taxfree_ustid' => '0',
                        'taxfree_ustid_checked' => '0',
                        'active' => '0',
                        'iso3' => 'NAM',
                        'display_state_in_registration' => '0',
                        'force_state_in_registration' => '0',
                        'countryarea' => 'welt',
                    ],
                    'countryShipping' => [
                        'id' => '20',
                        'countryname' => 'Namibia',
                        'countryiso' => 'NA',
                        'areaID' => '2',
                        'countryen' => 'NAMIBIA',
                        'position' => '10',
                        'notice' => '',
                        'shippingfree' => '0',
                        'taxfree' => '0',
                        'taxfree_ustid' => '0',
                        'taxfree_ustid_checked' => '0',
                        'active' => '0',
                        'iso3' => 'NAM',
                        'display_state_in_registration' => '0',
                        'force_state_in_registration' => '0',
                        'countryarea' => 'welt',
                    ],
                    'stateShipping' => ['id' => 0],
                ],
            ],
            $this->module->sGetUserData()
        );
    }

    /**
     * @covers \sAdmin::sGetUserData
     */
    public function testsGetUserDataWithLogin()
    {
        $customer = $this->createDummyCustomer();
        $this->session->offsetSet('sUserId', $customer->getId());
        $this->session->offsetUnset('sState');

        $result = $this->module->sGetUserData();

        $expectedData = [
            'billingaddress' => [
                'company' => '',
                'department' => '',
                'salutation' => 'mr',
                'firstname' => 'Max',
                'lastname' => 'Mustermann',
                'street' => 'Kraftweg, 22',
                'zipcode' => '12345',
                'city' => 'Musterhausen',
                'phone' => '',
                'countryID' => '2',
                'stateID' => null,
                'ustid' => '',
                'title' => null,
                'additional_address_line1' => 'IT-Department',
                'additional_address_line2' => 'Second Floor',
                'attributes' => [
                    'text1' => 'Freitext1',
                    'text2' => 'Freitext2',
                    'text3' => null,
                    'text4' => null,
                    'text5' => null,
                    'text6' => null,
                ],
            ],
            'additional' => [
                'country' => [
                    'countryname' => 'Germany',
                    'countryiso' => 'DE',
                    'areaID' => '1',
                    'countryen' => 'GERMANY',
                    'position' => '1',
                    'notice' => '',
                    'shippingfree' => '0',
                    'taxfree' => '0',
                    'taxfree_ustid' => '0',
                    'taxfree_ustid_checked' => '0',
                    'active' => '1',
                    'iso3' => 'DEU',
                    'display_state_in_registration' => '0',
                    'force_state_in_registration' => '0',
                    'countryarea' => 'deutschland',
                ],
                'state' => [],
                'user' => [
                    'password' => $customer->getPassword(),
                    'encoder' => 'bcrypt',
                    'email' => $customer->getEmail(),
                    'active' => '1',
                    'accountmode' => '0',
                    'confirmationkey' => '',
                    'paymentID' => 5,
                    'customernumber' => $customer->getNumber(),
                    'firstlogin' => $customer->getFirstLogin()->format('Y-m-d'),
                    'lastlogin' => $customer->getLastLogin()->format('Y-m-d H:i:s'),
                    'sessionID' => '',
                    'newsletter' => 0,
                    'validation' => '',
                    'affiliate' => '0',
                    'customergroup' => 'EK',
                    'paymentpreset' => '0',
                    'language' => '1',
                    'subshopID' => '1',
                    'referer' => '',
                    'pricegroupID' => null,
                    'internalcomment' => '',
                    'failedlogins' => '0',
                    'lockeduntil' => null,
                    'default_billing_address_id' => $customer->getDefaultBillingAddress() ? $customer->getDefaultBillingAddress()->getId() : null,
                    'default_shipping_address_id' => $customer->getDefaultShippingAddress() ? $customer->getDefaultShippingAddress()->getId() : null,
                    'birthday' => '1986-12-20',
                    'firstname' => 'Max',
                    'lastname' => 'Mustermann',
                    'salutation' => 'mr',
                    'title' => null,
                ],
                'countryShipping' => [
                    'countryname' => 'Australien',
                    'countryiso' => 'AU',
                    'areaID' => '2',
                    'countryen' => 'AUSTRALIA',
                    'position' => '10',
                    'notice' => '',
                    'shippingfree' => '0',
                    'taxfree' => '0',
                    'taxfree_ustid' => '0',
                    'taxfree_ustid_checked' => '0',
                    'active' => '1',
                    'iso3' => 'AUS',
                    'display_state_in_registration' => '0',
                    'force_state_in_registration' => '0',
                    'countryarea' => 'welt',
                ],
                'stateShipping' => [],
                'payment' => [
                    'name' => 'prepayment',
                    'description' => 'Vorkasse',
                    'template' => 'prepayment.tpl',
                    'class' => 'prepayment.php',
                    'table' => '',
                    'hide' => '0',
                    'additionaldescription' => 'Sie zahlen einfach vorab und erhalten die Ware bequem und günstig bei Zahlungseingang nach Hause geliefert.',
                    'debit_percent' => '0',
                    'surcharge' => '0',
                    'surchargestring' => '',
                    'position' => '1',
                    'active' => '1',
                    'esdactive' => '0',
                    'mobile_inactive' => '0',
                    'embediframe' => '',
                    'hideprospect' => '0',
                    'action' => null,
                    'pluginID' => null,
                    'source' => null,
                ],
            ],
            'shippingaddress' => [
                'company' => 'Widgets Inc.',
                'department' => '',
                'salutation' => 'Mr',
                'firstname' => 'Max',
                'lastname' => 'Mustermann',
                'street' => 'Merkel Strasse, 10',
                'zipcode' => '98765',
                'city' => 'Musterhausen',
                'countryID' => '4',
                'stateID' => null,
                'title' => null,
                'additional_address_line1' => 'Sales-Department',
                'additional_address_line2' => 'Third Floor',
                'attributes' => [
                    'text1' => 'Freitext1',
                    'text2' => 'Freitext2',
                    'text3' => null,
                    'text4' => null,
                    'text5' => null,
                    'text6' => null,
                ],
            ],
        ];

        $this->assertArray($expectedData, $result);

        $this->deleteDummyCustomer($customer);
    }

    /**
     * @covers \sAdmin::sManageRisks
     * @covers \sAdmin::sRiskORDERVALUELESS
     * @covers \sAdmin::sRiskORDERVALUEMORE
     * @covers \sAdmin::sRiskCUSTOMERGROUPIS
     * @covers \sAdmin::sRiskCUSTOMERGROUPISNOT
     * @covers \sAdmin::sRiskZIPCODE
     * @covers \sAdmin::sRiskBILLINGZIPCODE
     * @covers \sAdmin::sRiskZONEIS
     * @covers \sAdmin::sRiskBILLINGZONEIS
     * @covers \sAdmin::sRiskZONEISNOT
     * @covers \sAdmin::sRiskBILLINGZONEISNOT
     * @covers \sAdmin::sRiskLANDIS
     * @covers \sAdmin::sRiskBILLINGLANDIS
     * @covers \sAdmin::sRiskLANDISNOT
     * @covers \sAdmin::sRiskBILLINGLANDISNOT
     * @covers \sAdmin::sRiskNEWCUSTOMER
     * @covers \sAdmin::sRiskORDERPOSITIONSMORE
     * @covers \sAdmin::sRiskATTRIS
     * @covers \sAdmin::sRiskATTRISNOT
     * @covers \sAdmin::sRiskDUNNINGLEVELONE
     * @covers \sAdmin::sRiskDUNNINGLEVELTWO
     * @covers \sAdmin::sRiskDUNNINGLEVELTHREE
     * @covers \sAdmin::sRiskINKASSO
     * @covers \sAdmin::sRiskLASTORDERLESS
     * @covers \sAdmin::sRiskARTICLESFROM
     * @covers \sAdmin::sRiskLASTORDERSLESS
     * @covers \sAdmin::sRiskPREGSTREET
     * @covers \sAdmin::sRiskDIFFER
     * @covers \sAdmin::sRiskCUSTOMERNR
     * @covers \sAdmin::sRiskLASTNAME
     * @covers \sAdmin::sRiskSUBSHOP
     * @covers \sAdmin::sRiskSUBSHOPNOT
     * @covers \sAdmin::sRiskCURRENCIESISOIS
     * @covers \sAdmin::sRiskCURRENCIESISOISNOT
     */
    public function testsManageRisks()
    {
        $customer = $this->createDummyCustomer();
        $this->session->offsetSet('sUserId', $customer->getId());

        $basket = [
            'content' => 1,
            'AmountNumeric' => 10,
        ];
        $user = $this->module->sGetUserData();

        $date = new DateTime();

        // Inject demo data
        $orderData = [
            'ordernumber' => uniqid(rand()),
            'userID' => $customer->getId(),
            'invoice_amount' => '37.99',
            'invoice_amount_net' => '31.92',
            'invoice_shipping' => '0',
            'invoice_shipping_net' => '0',
            'ordertime' => $date->format('Y-m-d H:i:s'),
            'status' => '0',
            'cleared' => '17',
            'paymentID' => '4',
            'transactionID' => '',
            'comment' => '',
            'customercomment' => '',
            'internalcomment' => '',
            'net' => '0',
            'taxfree' => '0',
            'partnerID' => '',
            'temporaryID' => '',
            'referer' => '',
            'cleareddate' => null,
            'cleared' => 16,
            'trackingcode' => '',
            'language' => '2',
            'dispatchID' => '9',
            'currency' => 'EUR',
            'currencyFactor' => '1',
            'subshopID' => '1',
            'remote_addr' => '127.0.0.1',
        ];

        Shopware()->Db()->insert('s_order', $orderData);
        $orderId = Shopware()->Db()->lastInsertId();

        // No rules, returns false
        $this->assertFalse($this->module->sManageRisks(2, $basket, $user));

        // Test all rules

        // sRiskORDERVALUELESS
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'ORDERVALUELESS',
                'value1' => 20,
            ]
        );
        $firstTestRuleId = Shopware()->Db()->lastInsertId();
        $this->assertTrue($this->module->sManageRisks(2, $basket, $user));

        // sRiskORDERVALUEMORE
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'ORDERVALUEMORE',
                'value1' => 20,
            ]
        );
        // Test 'OR' logic between different rules (only one needs to be true)
        $this->assertTrue($this->module->sManageRisks(2, $basket, $user));

        // Deleting the first rule, only a false one is left
        Shopware()->Db()->delete('s_core_rulesets', 'id = ' . $firstTestRuleId);
        $this->assertFalse($this->module->sManageRisks(2, $basket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskCUSTOMERGROUPIS
        // sRiskCUSTOMERGROUPISNOT
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'CUSTOMERGROUPIS',
                'value1' => 'EK',
                'rule2' => 'CUSTOMERGROUPISNOT',
                'value2' => 'EK',
            ]
        );

        // Test 'AND' logic between the two parts of the same rule (both need to be true)
        $this->assertFalse($this->module->sManageRisks(2, $basket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskZIPCODE
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'ZIPCODE',
                'value1' => '98765',
            ]
        );
        $this->assertTrue($this->module->sManageRisks(2, $basket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskBILLINGZIPCODE
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'BILLINGZIPCODE',
                'value1' => '12345',
            ]
        );

        $this->assertTrue($this->module->sManageRisks(2, $basket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskZONEIS
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'ZONEIS',
                'value1' => '12345',
            ]
        );
        $this->assertFalse($this->module->sManageRisks(2, $basket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskZONEISNOT
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'ZONEISNOT',
                'value1' => '12345',
            ]
        );
        $this->assertTrue($this->module->sManageRisks(2, $basket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskLANDIS
        // sRiskLANDISNOT
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'LANDIS',
                'value1' => 'AU',
                'rule2' => 'LANDISNOT',
                'value2' => 'UK',
            ]
        );
        $this->assertTrue($this->module->sManageRisks(2, $basket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskBILLINGLANDIS
        // sRiskBILLINGLANDISNOT
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'BILLINGLANDIS',
                'value1' => 'DE',
                'rule2' => 'BILLINGLANDISNOT',
                'value2' => 'UK',
            ]
        );
        $this->assertTrue($this->module->sManageRisks(2, $basket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskNEWCUSTOMER
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'NEWCUSTOMER',
            ]
        );
        $this->assertTrue($this->module->sManageRisks(2, $basket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskORDERPOSITIONSMORE
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'ORDERPOSITIONSMORE',
                'value1' => '2',
            ]
        );
        $this->assertFalse($this->module->sManageRisks(2, $basket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        $this->module->sSYSTEM->sSESSION_ID = uniqid(rand());
        $this->session->offsetSet('sessionId', $this->module->sSYSTEM->sSESSION_ID);
        $this->basketModule->sAddArticle('SW10118.8');

        // sRiskATTRIS
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'ATTRIS',
                'value1' => '1|0',
            ]
        );

        $fullBasket = $this->basketModule->sGetBasket();
        $this->assertTrue($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        $this->basketModule->sAddArticle('SW10118.8');
        // sRiskATTRISNOT
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'ATTRISNOT',
                'value1' => '17|null',
            ]
        );
        $this->assertFalse($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskDUNNINGLEVELONE
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'DUNNINGLEVELONE',
            ]
        );
        $this->assertFalse($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskDUNNINGLEVELTWO
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'DUNNINGLEVELTWO',
            ]
        );
        $this->assertFalse($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskDUNNINGLEVELTHREE
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'DUNNINGLEVELTHREE',
            ]
        );
        $this->assertFalse($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskINKASSO
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'INKASSO',
            ]
        );
        $this->assertTrue($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskLASTORDERLESS
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'LASTORDERLESS',
                'value1' => '1',
            ]
        );
        $this->assertTrue($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskARTICLESFROM
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'ARTICLESFROM',
                'value1' => '1',
            ]
        );
        $this->assertFalse($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskARTICLESFROM
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'ARTICLESFROM',
                'value1' => '9',
            ]
        );
        $this->assertTrue($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskLASTORDERSLESS
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'LASTORDERSLESS',
                'value1' => '9',
            ]
        );
        $this->assertTrue($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskLASTORDERSLESS
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'LASTORDERSLESS',
                'value1' => '0',
            ]
        );
        $this->assertFalse($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskPREGSTREET
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'PREGSTREET',
                'value1' => 'Merkel',
            ]
        );
        $this->assertTrue($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskPREGSTREET
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'PREGSTREET',
                'value1' => 'Google',
            ]
        );
        $this->assertFalse($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskPREGBILLINGSTREET
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'PREGBILLINGSTREET',
                'value1' => 'Google',
            ]
        );
        $this->assertFalse($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskDIFFER
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'DIFFER',
            ]
        );
        $this->assertTrue($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskCUSTOMERNR
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'CUSTOMERNR',
                'value1' => $customer->getNumber(),
            ]
        );
        $this->assertTrue($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskCUSTOMERNR
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'CUSTOMERNR',
                'value1' => 'ThisIsNeverGoingToBeACustomerNumber',
            ]
        );
        $this->assertFalse($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskLASTNAME
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'LASTNAME',
                'value1' => 'Mustermann',
            ]
        );
        $this->assertTrue($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskLASTNAME
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'LASTNAME',
                'value1' => 'NotMustermann',
            ]
        );
        $this->assertFalse($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskSUBSHOP
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'SUBSHOP',
                'value1' => '1',
            ]
        );
        $this->assertTrue($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskSUBSHOP
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'SUBSHOP',
                'value1' => '2',
            ]
        );
        $this->assertFalse($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskSUBSHOPNOT
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'SUBSHOPNOT',
                'value1' => '2',
            ]
        );
        $this->assertTrue($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskSUBSHOPNOT
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'SUBSHOPNOT',
                'value1' => '1',
            ]
        );
        $this->assertFalse($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskCURRENCIESISOIS
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'CURRENCIESISOIS',
                'value1' => 'eur',
            ]
        );
        $this->assertTrue($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskCURRENCIESISOIS
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'CURRENCIESISOIS',
                'value1' => 'yen',
            ]
        );
        $this->assertFalse($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskCURRENCIESISOISNOT
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'CURRENCIESISOISNOT',
                'value1' => 'eur',
            ]
        );
        $this->assertFalse($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        // sRiskCURRENCIESISOISNOT
        Shopware()->Db()->insert(
            's_core_rulesets',
            [
                'paymentID' => 2,
                'rule1' => 'CURRENCIESISOISNOT',
                'value1' => 'yen',
            ]
        );
        $this->assertTrue($this->module->sManageRisks(2, $fullBasket, $user));
        Shopware()->Db()->delete('s_core_rulesets', 'id >= ' . $firstTestRuleId);

        Shopware()->Db()->delete('s_order', 'id = ' . $orderId);
        $this->deleteDummyCustomer($customer);
    }

    /**
     * @covers \sAdmin::sNewsletterSubscription
     */
    public function testsNewsletterSubscriptionWithPostData()
    {
        // Test subscribe with empty post field and empty address, fail validation
        $this->front->Request()->setPost('newsletter', '');
        $result = $this->module->sNewsletterSubscription('');
        $this->assertEquals(
            [
                'code' => 5,
                'message' => $this->snippetManager->getNamespace('frontend/account/internalMessages')
                        ->get('ErrorFillIn', 'Please fill in all red fields'),
                'sErrorFlag' => ['newsletter' => true],
            ],
            $result
        );
    }

    /**
     * @covers \sAdmin::sNewsletterSubscription
     */
    public function testsNewsletterSubscription()
    {
        $validAddress = uniqid(rand()) . '@shopware.com';

        // Test unsubscribe with non existing email, fail
        $result = $this->module->sNewsletterSubscription(uniqid(rand()) . '@shopware.com', true);
        $this->assertEquals(
            [
                'code' => 4,
                'message' => $this->snippetManager->getNamespace('frontend/account/internalMessages')
                        ->get('NewsletterFailureNotFound', 'This mail address could not be found'),
            ],
            $result
        );

        // Test unsubscribe with empty post field, fail validation
        $result = $this->module->sNewsletterSubscription('', true);
        $this->assertEquals(
            [
                'code' => 6,
                'message' => $this->snippetManager->getNamespace('frontend/account/internalMessages')
                        ->get('NewsletterFailureMail', 'Enter eMail address'),
            ],
            $result
        );

        // Test with empty field, fail validation
        $result = $this->module->sNewsletterSubscription('');
        $this->assertEquals(
            [
                'code' => 6,
                'message' => $this->snippetManager->getNamespace('frontend/account/internalMessages')
                        ->get('NewsletterFailureMail', 'Enter eMail address'),
            ],
            $result
        );

        // Test with malformed email, fail validation
        $result = $this->module->sNewsletterSubscription('thisIsNotAValidEmailAddress');
        $this->assertEquals(
            [
                'code' => 1,
                'message' => $this->snippetManager->getNamespace('frontend/account/internalMessages')
                        ->get('NewsletterFailureInvalid', 'Enter valid eMail address'),
            ],
            $result
        );

        // Check that test email does not exist
        $this->assertFalse(
            Shopware()->Db()->fetchRow(
                'SELECT email, groupID FROM s_campaigns_mailaddresses WHERE email LIKE ?',
                [$validAddress]
            )
        );

        // Test with correct unique email, all ok
        $result = $this->module->sNewsletterSubscription($validAddress);
        $this->assertEquals(
            [
                'code' => 3,
                'message' => $this->snippetManager->getNamespace('frontend/account/internalMessages')
                        ->get('NewsletterSuccess', 'Thank you for receiving our newsletter'),
            ],
            $result
        );

        // Check that test email was inserted
        $this->assertEquals(
            [
                'email' => $validAddress,
                'groupID' => $this->config->get('sNEWSLETTERDEFAULTGROUP'),
            ],
            Shopware()->Db()->fetchRow(
                'SELECT email, groupID FROM s_campaigns_mailaddresses WHERE email LIKE ?',
                [$validAddress]
            )
        );
        $this->assertEquals(
            [
                [
                    'email' => $validAddress,
                    'groupID' => $this->config->get('sNEWSLETTERDEFAULTGROUP'),
                ],
            ],
            Shopware()->Db()->fetchAll(
                'SELECT email, groupID FROM s_campaigns_maildata WHERE email LIKE ?',
                [$validAddress]
            )
        );

        // Test with same email, fail
        $result = $this->module->sNewsletterSubscription($validAddress);
        $this->assertEquals(
            [
                'code' => 3,
                'message' => $this->snippetManager->getNamespace('frontend/account/internalMessages')
                        ->get('NewsletterSuccess', 'Thank you! We have entered your address.'),
            ],
            $result
        );

        // Test with same email in a different list, fail
        $groupId = rand(1, 9999);
        $result = $this->module->sNewsletterSubscription($validAddress, false, $groupId);
        $this->assertEquals(
            [
                'code' => 3,
                'message' => $this->snippetManager->getNamespace('frontend/account/internalMessages')
                        ->get('NewsletterSuccess', 'Thank you! We have entered your address.'),
            ],
            $result
        );

        // Check that test email address is still there, but now in two groups
        $this->assertEquals(
            [
                [
                    'email' => $validAddress,
                    'groupID' => $this->config->get('sNEWSLETTERDEFAULTGROUP'),
                ],
            ],
            Shopware()->Db()->fetchAll(
                'SELECT email, groupID FROM s_campaigns_mailaddresses WHERE email LIKE ?',
                [$validAddress]
            )
        );
        $this->assertEquals(
            [
                [
                    'email' => $validAddress,
                    'groupID' => $this->config->get('sNEWSLETTERDEFAULTGROUP'),
                ],
                [
                    'email' => $validAddress,
                    'groupID' => $groupId,
                ],
            ],
            Shopware()->Db()->fetchAll(
                'SELECT email, groupID FROM s_campaigns_maildata WHERE email LIKE ?',
                [$validAddress]
            )
        );

        // Test unsubscribe the same email, all ok
        $result = $this->module->sNewsletterSubscription($validAddress, true);
        $this->assertEquals(
            [
                'code' => 5,
                'message' => $this->snippetManager->getNamespace('frontend/account/internalMessages')
                        ->get('NewsletterMailDeleted', 'Your mail address was deleted'),
            ],
            $result
        );

        // Check that test email address was removed
        $this->assertFalse(
            Shopware()->Db()->fetchRow(
                'SELECT email, groupID FROM s_campaigns_mailaddresses WHERE email LIKE ?',
                [$validAddress]
            )
        );

        // But not completely from maildata
        $this->assertEquals(
            [
                [
                    'email' => $validAddress,
                    'groupID' => $groupId,
                ],
            ],
            Shopware()->Db()->fetchAll(
                'SELECT email, groupID FROM s_campaigns_maildata WHERE email LIKE ?',
                [$validAddress]
            )
        );

        Shopware()->Db()->delete(
            's_campaigns_maildata',
            'email LIKE "' . $validAddress . '"'
        );
    }

    /**
     * @covers \sAdmin::sGetCountry
     */
    public function testsGetCountry()
    {
        // Empty argument, return false
        $this->assertFalse($this->module->sGetCountry(''));

        // No matching country, return empty array
        $this->assertEquals([], $this->module->sGetCountry(-1));

        // Valid country returns valid data
        $result = $this->module->sGetCountry('de');
        $this->assertEquals(
            [
                'id' => '2',
                'countryID' => '2',
                'countryname' => 'Deutschland',
                'countryiso' => 'DE',
                'countryarea' => 'deutschland',
                'countryen' => 'GERMANY',
                'position' => '1',
                'notice' => '',
                'shippingfree' => '0',
            ],
            $result
        );

        // Fetching for id or iso code gives the same result
        $this->assertEquals(
            $this->module->sGetCountry($result['id']),
            $result
        );
    }

    /**
     * @covers \sAdmin::sGetPaymentMean
     */
    public function testsGetPaymentmean()
    {
        // Empty argument, return false
        $this->assertFalse($this->module->sGetPaymentMean(''));

        // No matching payment mean, return empty array
        $this->assertEquals(['country_surcharge' => []], $this->module->sGetPaymentMean(-1));

        // Valid country returns valid data
        $result = $this->module->sGetPaymentMean(
            Shopware()->Db()->fetchOne('SELECT id FROM s_core_paymentmeans WHERE name = "prepayment"')
        );

        $this->assertEquals(
            [
                'id' => '5',
                'name' => 'prepayment',
                'description' => 'Vorkasse',
                'template' => 'prepayment.tpl',
                'class' => 'prepayment.php',
                'table' => '',
                'hide' => '0',
                'additionaldescription' => 'Sie zahlen einfach vorab und erhalten die Ware bequem und günstig bei Zahlungseingang nach Hause geliefert.',
                'debit_percent' => '0',
                'surcharge' => '0',
                'surchargestring' => '',
                'position' => '1',
                'active' => '1',
                'esdactive' => '0',
                'mobile_inactive' => '0',
                'embediframe' => '',
                'hideprospect' => '0',
                'action' => null,
                'pluginID' => null,
                'source' => null,
                'country_surcharge' => [],
                'risk_rules' => null,
            ],
            $result
        );

        // Fetching for id or iso code gives the same result
        $this->assertEquals(
            $this->module->sGetPaymentMean($result['name']),
            $result
        );
    }

    /**
     * @covers \sAdmin::sGetDispatchBasket
     */
    public function testsGetDispatchBasket()
    {
        // No basket, return false
        $this->assertFalse($this->module->sGetDispatchBasket());

        $this->module->sSYSTEM->sSESSION_ID = uniqid(rand());
        $this->session->offsetSet('sessionId', $this->module->sSYSTEM->sSESSION_ID);
        $this->basketModule->sAddArticle('SW10118.8');

        // With the correct data, return properly formatted array
        // This is a big query function
        $result = $this->module->sGetDispatchBasket();
        $this->assertArrayHasKey('instock', $result);
        $this->assertArrayHasKey('stockmin', $result);
        $this->assertArrayHasKey('laststock', $result);
        $this->assertArrayHasKey('weight', $result);
        $this->assertArrayHasKey('count_article', $result);
        $this->assertArrayHasKey('shippingfree', $result);
        $this->assertArrayHasKey('amount', $result);
        $this->assertArrayHasKey('amount_net', $result);
        $this->assertArrayHasKey('amount_display', $result);
        $this->assertArrayHasKey('length', $result);
        $this->assertArrayHasKey('height', $result);
        $this->assertArrayHasKey('width', $result);
        $this->assertArrayHasKey('userID', $result);
        $this->assertArrayHasKey('has_topseller', $result);
        $this->assertArrayHasKey('has_comment', $result);
        $this->assertArrayHasKey('has_esd', $result);
        $this->assertArrayHasKey('max_tax', $result);
        $this->assertArrayHasKey('basketStateId', $result);
        $this->assertArrayHasKey('countryID', $result);
        $this->assertArrayHasKey('paymentID', $result);
        $this->assertArrayHasKey('customergroupID', $result);
        $this->assertArrayHasKey('multishopID', $result);
        $this->assertArrayHasKey('sessionID', $result);
    }

    /**
     * @covers \sAdmin::sGetPremiumDispatches
     */
    public function testsGetPremiumDispatches()
    {
        // No basket, return empty array,
        $this->assertEquals([], $this->module->sGetPremiumDispatches());

        $this->module->sSYSTEM->sSESSION_ID = uniqid(rand());
        $this->session->offsetSet('sessionId', $this->module->sSYSTEM->sSESSION_ID);
        $this->basketModule->sAddArticle('SW10118.8');

        $result = $this->module->sGetPremiumDispatches();

        $this->assertGreaterThan(0, count($result));
        foreach ($result as $dispatch) {
            $this->assertArrayHasKey('id', $dispatch);
            $this->assertArrayHasKey('name', $dispatch);
            $this->assertArrayHasKey('description', $dispatch);
            $this->assertArrayHasKey('calculation', $dispatch);
            $this->assertArrayHasKey('status_link', $dispatch);
        }
    }

    /**
     * @covers \sAdmin::sGetPremiumDispatchSurcharge
     */
    public function testsGetPremiumDispatchSurcharge()
    {
        // No basket, return false,
        $this->assertFalse($this->module->sGetPremiumDispatchSurcharge(null));

        $this->module->sSYSTEM->sSESSION_ID = uniqid(rand());
        $this->session->offsetSet('sessionId', $this->module->sSYSTEM->sSESSION_ID);
        $this->basketModule->sAddArticle('SW10010');
        $fullBasket = $this->module->sGetDispatchBasket();

        $result = $this->module->sGetPremiumDispatchSurcharge($fullBasket);
        $this->assertEquals(0, $result);
    }

    /**
     * @param array $expected
     * @param array $actual
     */
    private function assertArray($expected, $actual)
    {
        foreach ($expected as $key => $value) {
            $this->assertArrayHasKey($key, $actual);
            $currentActual = $actual[$key];

            if (is_array($value)) {
                $this->assertArray($value, $currentActual);
            } else {
                $this->assertEquals($value, $currentActual);
            }
        }
    }

    /**
     * Create dummy customer entity
     *
     * @return \Shopware\Models\Customer\Customer
     */
    private function createDummyCustomer()
    {
        $date = new DateTime();
        $date->modify('-8 days');
        $lastLogin = $date->format(DateTime::ISO8601);

        $birthday = DateTime::createFromFormat('Y-m-d', '1986-12-20')->format(DateTime::ISO8601);

        $testData = [
            'password' => 'fooobar',
            'email' => uniqid(rand()) . 'test@foobar.com',
            'customernumber' => 'dummy customer number',
            'lastlogin' => $lastLogin,

            'salutation' => 'mr',
            'firstname' => 'Max',
            'lastname' => 'Mustermann',
            'birthday' => $birthday,

            'billing' => [
                'salutation' => 'mr',
                'firstname' => 'Max',
                'lastname' => 'Mustermann',
                'attribute' => [
                    'text1' => 'Freitext1',
                    'text2' => 'Freitext2',
                ],
                'zipcode' => '12345',
                'city' => 'Musterhausen',
                'street' => 'Kraftweg, 22',
                'country' => '2',
                'additionalAddressLine1' => 'IT-Department',
                'additionalAddressLine2' => 'Second Floor',
            ],

            'shipping' => [
                'salutation' => 'Mr',
                'company' => 'Widgets Inc.',
                'firstname' => 'Max',
                'lastname' => 'Mustermann',
                'zipcode' => '98765',
                'city' => 'Musterhausen',
                'street' => 'Merkel Strasse, 10',
                'country' => '4',
                'attribute' => [
                    'text1' => 'Freitext1',
                    'text2' => 'Freitext2',
                ],
                'additionalAddressLine1' => 'Sales-Department',
                'additionalAddressLine2' => 'Third Floor',
            ],

            'debit' => [
                'account' => 'Fake Account',
                'bankCode' => '55555555',
                'bankName' => 'Fake Bank',
                'accountHolder' => 'Max Mustermann',
            ],
        ];

        $customerResource = new \Shopware\Components\Api\Resource\Customer();
        $customerResource->setManager(Shopware()->Models());

        return $customerResource->create($testData);
    }

    /**
     * Deletes all dummy customer entity
     */
    private function deleteDummyCustomer(\Shopware\Models\Customer\Customer $customer)
    {
        $billingId = Shopware()->Db()->fetchOne('SELECT id FROM s_user_billingaddress WHERE userID = ?', [$customer->getId()]);
        $shippingId = Shopware()->Db()->fetchOne('SELECT id FROM s_user_shippingaddress WHERE userID = ?', [$customer->getId()]);

        if ($billingId) {
            Shopware()->Db()->delete('s_user_billingaddress_attributes', 'billingID = ' . $billingId);
            Shopware()->Db()->delete('s_user_billingaddress', 'id = ' . $billingId);
        }
        if ($shippingId) {
            Shopware()->Db()->delete('s_user_shippingaddress_attributes', 'shippingID = ' . $shippingId);
            Shopware()->Db()->delete('s_user_shippingaddress', 'id = ' . $shippingId);
        }
        Shopware()->Db()->delete('s_core_payment_data', 'user_id = ' . $customer->getId());
        Shopware()->Db()->delete('s_user_attributes', 'userID = ' . $customer->getId());
        Shopware()->Db()->delete('s_user', 'id = ' . $customer->getId());
    }
}
