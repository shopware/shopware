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

namespace Shopware\Tests\Functional\Bundle\AccountBundle\Controller;

use Shopware\Models\Customer\Customer;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class RegisterTest extends \Enlight_Components_Test_Controller_TestCase
{
    const TEST_MAIL = 'unittest@mail.com';
    const SAVE_URL = '/register/saveRegister/sTarget/account/sTargetAction/index';

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        Shopware()->Container()->reset('router');
    }

    public function setUp()
    {
        parent::setUp();
        $this->deleteCustomer(self::TEST_MAIL);
        Shopware()->Container()->get('models')->clear();
    }

    protected function tearDown()
    {
        parent::tearDown();
        $this->deleteCustomer(self::TEST_MAIL);
        Shopware()->Container()->get('models')->clear();
    }

    public function testSimpleRegistration()
    {
        $this->Request()->setMethod('POST');
        $this->Request()->setPost([
            'register' => [
                'personal' => $this->getPersonalData(),
                'billing' => $this->getBillingData(),
            ],
        ]);

        $this->sendRequestAndAssertCustomer(
            self::TEST_MAIL,
            [
                'firstname' => 'first name',
                'lastname' => 'last name',
                'salutation' => 'mr',
                'email' => self::TEST_MAIL,
            ],
            [
                'street' => 'street',
                'zipcode' => 'zipcode',
                'city' => 'city',
                'country_id' => 2,
            ],
            [
                'street' => 'street',
                'zipcode' => 'zipcode',
                'city' => 'city',
                'country_id' => 2,
            ]
        );
    }

    public function testRegistrationWithShipping()
    {
        $this->Request()->setMethod('POST');
        $this->Request()->setPost([
            'register' => [
                'personal' => $this->getPersonalData(),
                'billing' => $this->getBillingData([
                    'shippingAddress' => 1,
                ]),
                'shipping' => $this->getShippingData(),
            ],
        ]);

        $this->sendRequestAndAssertCustomer(
            self::TEST_MAIL,
            ['firstname' => 'first name'],
            ['street' => 'street'],
            [
                'salutation' => 'ms',
                'company' => 'company',
                'department' => 'department',
                'firstname' => 'second first name',
                'lastname' => 'second last name',
                'street' => 'street 2',
                'zipcode' => 'zipcode 2',
                'city' => 'city 2',
                'country_id' => 3,
            ]
        );
    }

    public function testCompanyRegistration()
    {
        $this->Request()->setMethod('POST');
        $this->Request()->setPost([
            'register' => [
                'personal' => $this->getPersonalData([
                    'customer_type' => Customer::CUSTOMER_TYPE_BUSINESS,
                ]),
                'billing' => $this->getBillingData([
                    'vatId' => 'xxxxxxxxxxxxxx',
                    'company' => 'company',
                    'department' => 'department',
                ]),
            ],
        ]);

        $this->sendRequestAndAssertCustomer(
            self::TEST_MAIL,
            [
                'firstname' => 'first name',
            ],
            [
                'street' => 'street',
                'ustid' => 'xxxxxxxxxxxxxx',
                'company' => 'company',
                'department' => 'department',
            ]
        );
    }

    public function testFastRegistration()
    {
        $this->Request()->setMethod('POST');
        $this->Request()->setPost([
            'register' => [
                'personal' => $this->getPersonalData([
                    'password' => null,
                    'accountmode' => Customer::ACCOUNT_MODE_FAST_LOGIN,
                ]),
                'billing' => $this->getBillingData(),
            ],
        ]);

        $this->sendRequestAndAssertCustomer(
            self::TEST_MAIL,
            [
                'firstname' => 'first name',
                'lastname' => 'last name',
                'salutation' => 'mr',
                'email' => self::TEST_MAIL,
                'accountmode' => 1,
            ],
            [
                'street' => 'street',
                'zipcode' => 'zipcode',
                'city' => 'city',
                'country_id' => 2,
            ]
        );
    }

    public function testDefaultPayment()
    {
        Shopware()->Session()->offsetSet('sPaymentID', 6);

        $this->Request()->setMethod('POST');
        $this->Request()->setPost([
            'register' => [
                'personal' => $this->getPersonalData(),
                'billing' => $this->getBillingData(),
            ],
        ]);

        $this->sendRequestAndAssertCustomer(
            self::TEST_MAIL,
            [
                'firstname' => 'first name',
                'paymentID' => 6,
                'lastname' => 'last name',
                'salutation' => 'mr',
                'email' => self::TEST_MAIL,
            ],
            [
                'street' => 'street',
                'zipcode' => 'zipcode',
                'city' => 'city',
                'country_id' => 2,
            ]
        );
    }

    private function sendRequestAndAssertCustomer($email, $personal, $billing = [], $shipping = [])
    {
        $response = $this->dispatch(self::SAVE_URL);

        $this->assertEquals(302, $response->getHttpResponseCode());

        $this->assertStringEndsWith(
            '/account',
            $this->getHeaderLocation($response)
        );

        $session = Shopware()->Container()->get('session');
        $this->assertNotEmpty($session->offsetGet('sUserId'));

        $customer = Shopware()->Container()->get('dbal_connection')->fetchAssoc(
            'SELECT * FROM s_user WHERE email = :mail LIMIT 1',
            [':mail' => $email]
        );
        $this->assertNotEmpty($customer);

        if (!empty($personal)) {
            foreach ($personal as $key => $value) {
                $this->assertArrayHasKey($key, $customer);
                $this->assertEquals($value, $customer[$key]);
            }
        }

        if (!empty($billing)) {
            $this->assertAddress($email, $billing);
        }

        if (!empty($shipping)) {
            $this->assertAddress($email, $shipping, 'shipping');
        }
    }

    private function deleteCustomer($email)
    {
        Shopware()->Container()->get('dbal_connection')->executeQuery(
            'DELETE FROM s_user WHERE email = :mail',
            [':mail' => $email]
        );
    }

    /**
     * @param array $data
     *
     * @return array
     */
    private function getPersonalData($data = [])
    {
        return array_merge([
            'salutation' => 'mr',
            'customer_type' => Customer::CUSTOMER_TYPE_PRIVATE,
            'password' => 'defaultpassword',
            'email' => self::TEST_MAIL,
            'firstname' => 'first name',
            'lastname' => 'last name',
            'accountmode' => Customer::ACCOUNT_MODE_CUSTOMER,
        ], $data);
    }

    private function getShippingData($data = [])
    {
        return array_merge([
            'salutation' => 'ms',
            'company' => 'company',
            'department' => 'department',
            'firstname' => 'second first name',
            'lastname' => 'second last name',
            'street' => 'street 2',
            'zipcode' => 'zipcode 2',
            'city' => 'city 2',
            'country' => 3,
        ], $data);
    }

    private function getBillingData($data = [])
    {
        return array_merge([
            'street' => 'street',
            'zipcode' => 'zipcode',
            'city' => 'city',
            'country' => 2,
            'country_state_2' => 6,
        ], $data);
    }

    /**
     * @param string $email
     * @param array  $data
     * @param string $type
     */
    private function assertAddress($email, $data, $type = 'billing')
    {
        $table = 's_user_billingaddress';
        $column = 'default_billing_address_id';
        if ($type !== 'billing') {
            $table = 's_user_shippingaddress';
            $column = 'default_shipping_address_id';
        }

        $legacy = Shopware()->Container()->get('dbal_connection')->fetchAssoc(
            'SELECT address.id FROM ' . $table . ' address, s_user user WHERE user.email = :mail AND address.userID = user.id LIMIT 1',
            [':mail' => $email]
        );
        $address = Shopware()->Container()->get('dbal_connection')->fetchAssoc(
            'SELECT address.* FROM s_user_addresses address, s_user user WHERE user.' . $column . ' = address.id AND user.email = :mail',
            [':mail' => $email]
        );

        $this->assertNotEmpty($legacy);
        $this->assertNotEmpty($address);

        foreach ($data as $key => $value) {
            $this->assertArrayHasKey($key, $address);
            $this->assertEquals($value, $address[$key]);
        }
    }

    /**
     * @param \Enlight_Controller_Response_Response $response
     *
     * @return null|string
     */
    private function getHeaderLocation(\Enlight_Controller_Response_Response $response)
    {
        $headers = $response->getHeaders();
        foreach ($headers as $header) {
            if ($header['name'] == 'Location') {
                return $header['value'];
            }
        }

        return null;
    }
}
