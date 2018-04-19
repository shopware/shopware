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

use ShopwarePlugin\PaymentMethods\Components\SepaPaymentMethod;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class Shopware_Tests_Plugins_Core_PaymentMethods_SepaPaymentMethod extends Enlight_Components_Test_Plugin_TestCase
{
    /**
     * @var SepaPaymentMethod
     */
    protected static $sepaPaymentMethod;

    protected static $sepaStatus;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        $helper = Shopware();
        $loader = $helper->Loader();

        $pluginDir = $helper->DocPath() . 'engine/Shopware/Plugins/Default/Core/PaymentMethods';

        $loader->registerNamespace(
            'ShopwarePlugin\\PaymentMethods\\Components',
            $pluginDir . '/Components/'
        );

        //SEPA needs to be active for this. Also, we need to save existing status to later restore it
        $sepaPaymentMean = Shopware()->Models()
            ->getRepository('\Shopware\Models\Payment\Payment')
            ->findOneByName('Sepa');

        self::$sepaStatus = $sepaPaymentMean->getActive();

        $sepaPaymentMean->setActive(true);
        Shopware()->Models()->flush($sepaPaymentMean);

        self::$sepaPaymentMethod = new SepaPaymentMethod();
    }

    public static function tearDownAfterClass()
    {
        Shopware()->Models()
            ->getRepository('\Shopware\Models\Payment\Payment')
            ->findOneByName('Sepa')
            ->setActive(self::$sepaStatus);

        $paymentData = Shopware()->Models()
            ->getRepository('\Shopware\Models\Customer\PaymentData')
            ->findAll();
        foreach ($paymentData as $payment) {
            Shopware()->Models()->remove($payment);
        }

        $paymentInstances = Shopware()->Models()
            ->getRepository('\Shopware\Models\Payment\PaymentInstance')
            ->findAll();
        foreach ($paymentInstances as $paymentInstance) {
            Shopware()->Models()->remove($paymentInstance);
        }

        Shopware()->Models()->flush();
        parent::tearDownAfterClass();
    }

    public function testValidateEmptyGet()
    {
        $validationResult = self::$sepaPaymentMethod->validate([]);
        $this->assertTrue(is_array($validationResult));
        if (count($validationResult)) {
            $this->assertArrayHasKey('sErrorFlag', $validationResult);
            $this->assertArrayHasKey('sErrorMessages', $validationResult);
            $this->assertArrayHasKey('sSepaIban', $validationResult['sErrorFlag']);
            $this->assertArrayHasKey('sSepaBic', $validationResult['sErrorFlag']);
            $this->assertArrayHasKey('sSepaBankName', $validationResult['sErrorFlag']);
        }
    }

    public function testValidateFaultyIban()
    {
        $data = [
            'sSepaIban' => 'Some Invalid Iban',
            'sSepaBic' => 'Some Valid Bic',
            'sSepaBankName' => 'Some Valid Bank Name',
        ];

        $validationResult = self::$sepaPaymentMethod->validate($data);
        $this->assertTrue(is_array($validationResult));
        if (count($validationResult)) {
            $this->assertArrayHasKey('sErrorFlag', $validationResult);
            $this->assertArrayHasKey('sErrorMessages', $validationResult);
            $this->assertContains(Shopware()->Snippets()->getNamespace('frontend/plugins/payment/sepa')
                ->get('ErrorIBAN', 'Invalid IBAN'), $validationResult['sErrorMessages']);
            $this->assertFalse(array_key_exists('sSepaBic', $validationResult['sErrorFlag']));
            $this->assertFalse(array_key_exists('sSepaBankName', $validationResult['sErrorFlag']));
        }
    }

    public function testValidateCorrectData()
    {
        $data = [
            'sSepaIban' => 'AL47 2121 1009 0000 0002 3569 8741',
            'sSepaBic' => 'Some Valid Bic',
            'sSepaBankName' => 'Some Valid Bank Name',
        ];

        $validationResult = self::$sepaPaymentMethod->validate($data);
        $this->assertTrue(is_array($validationResult));
        $this->assertCount(0, $validationResult);
    }

    /**
     * Covers issue SW-7721
     */
    public function testCreatePaymentInstanceWithNoPaymentData()
    {
        $orderId = 57;
        $userId = 1;
        $paymentId = 6;
        Shopware()->Session()->sUserId = $userId;

        //for now, don't test email
        Shopware()->Config()->set('sepaSendEmail', false);

        self::$sepaPaymentMethod->createPaymentInstance($orderId, $userId, $paymentId);

        $paymentInstance = Shopware()->Models()
            ->getRepository('\Shopware\Models\Payment\PaymentInstance')
            ->findOneBy(['order' => $orderId, 'customer' => $userId, 'paymentMean' => $paymentId]);

        $this->assertInstanceOf('Shopware\Models\Payment\PaymentInstance', $paymentInstance);
        $this->assertInstanceOf('Shopware\Models\Order\Order', $paymentInstance->getOrder());
        $this->assertEquals(57, $paymentInstance->getOrder()->getId());
        $this->assertInstanceOf('Shopware\Models\Payment\Payment', $paymentInstance->getPaymentMean());
        $this->assertEquals('sepa', $paymentInstance->getPaymentMean()->getName());

        $this->assertNull($paymentInstance->getBankName());
        $this->assertNull($paymentInstance->getBic());
        $this->assertNull($paymentInstance->getIban());
        $this->assertNull($paymentInstance->getFirstName());
        $this->assertNull($paymentInstance->getLastName());
        $this->assertNull($paymentInstance->getAddress());
        $this->assertNull($paymentInstance->getZipCode());
        $this->assertNull($paymentInstance->getCity());
        $this->assertNotNull($paymentInstance->getAmount());

        Shopware()->Models()->remove($paymentInstance);
        Shopware()->Models()->flush($paymentInstance);
    }

    public function testSavePaymentDataInitialEmptyData()
    {
        self::$sepaPaymentMethod->savePaymentData(1, $this->Request());

        $lastPayment = self::$sepaPaymentMethod->getCurrentPaymentDataAsArray(1);
        $this->assertEquals(null, $lastPayment['sSepaBankName']);
        $this->assertEquals(null, $lastPayment['sSepaBic']);
        $this->assertEquals(null, $lastPayment['sSepaIban']);
        $this->assertEquals(false, $lastPayment['sSepaUseBillingData']);
    }

    /**
     * @depends testSavePaymentDataInitialEmptyData
     */
    public function testSavePaymentDataUpdatePrevious()
    {
        $this->Request()->setQuery([
            'sSepaIban' => 'AL47 2121 1009 0000 0002 3569 8741',
            'sSepaBic' => 'Some Valid Bic',
            'sSepaBankName' => 'Some Valid Bank Name',
            'sSepaUseBillingData' => 'true',
        ]);
        Shopware()->Front()->setRequest($this->Request());

        self::$sepaPaymentMethod->savePaymentData(1, $this->Request());

        $lastPayment = self::$sepaPaymentMethod->getCurrentPaymentDataAsArray(1);
        $this->assertEquals('Some Valid Bank Name', $lastPayment['sSepaBankName']);
        $this->assertEquals('Some Valid Bic', $lastPayment['sSepaBic']);
        $this->assertEquals('AL47212110090000000235698741', $lastPayment['sSepaIban']);
        $this->assertEquals(true, $lastPayment['sSepaUseBillingData']);
    }

    public function testCreatePaymentInstance()
    {
        $orderId = 57;
        $userId = 1;
        $paymentId = 6;
        Shopware()->Session()->sUserId = $userId;

        //for now, don't test email
        Shopware()->Config()->set('sepaSendEmail', false);

        self::$sepaPaymentMethod->createPaymentInstance($orderId, $userId, $paymentId);

        $paymentInstance = Shopware()->Models()
            ->getRepository('\Shopware\Models\Payment\PaymentInstance')
            ->findOneBy(['order' => $orderId, 'customer' => $userId, 'paymentMean' => $paymentId]);

        $this->assertInstanceOf('Shopware\Models\Payment\PaymentInstance', $paymentInstance);
        $this->assertInstanceOf('Shopware\Models\Order\Order', $paymentInstance->getOrder());
        $this->assertEquals(57, $paymentInstance->getOrder()->getId());
        $this->assertInstanceOf('Shopware\Models\Payment\Payment', $paymentInstance->getPaymentMean());
        $this->assertEquals('sepa', $paymentInstance->getPaymentMean()->getName());

        $this->assertEquals('Some Valid Bank Name', $paymentInstance->getBankName());
        $this->assertEquals('Some Valid Bic', $paymentInstance->getBic());
        $this->assertEquals('AL47212110090000000235698741', $paymentInstance->getIban());
        $this->assertEquals('Max', $paymentInstance->getFirstName());
        $this->assertEquals('Mustermann', $paymentInstance->getLastName());
        $this->assertEquals('Musterstr. 55', $paymentInstance->getAddress());
        $this->assertEquals('55555', $paymentInstance->getZipCode());
        $this->assertEquals('Musterhausen', $paymentInstance->getCity());
        $this->assertNotNull($paymentInstance->getAmount());
    }
}
