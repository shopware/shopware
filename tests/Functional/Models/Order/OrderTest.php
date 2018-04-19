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
class Shopware_Tests_Models_Order_OrderTest extends Enlight_Components_Test_TestCase
{
    /**
     * @var Shopware\Components\Model\ModelManager
     */
    protected $em;

    /**
     * @var Shopware\Models\User\Repository
     */
    protected $repo;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->em = Shopware()->Models();
        $this->repo = Shopware()->Models()->getRepository('Shopware\Models\Order\Order');

        Shopware()->Container()->set('Auth', new ZendAuthMock());
    }

    public function testUpdateOrderHistory()
    {
        $order = $this->createOrder();

        $previousPaymentStatus = $order->getPaymentStatus();
        $previousOrderStatus = $order->getOrderStatus();

        $this->orderIsSaved($order);

        $history = $this->thenRetrieveHistoryOf($order);
        $this->assertCount(0, $history);

        $paymentStatusInProgress = $this->em->getReference('\Shopware\Models\Order\Status', 1);
        $orderStatusReserved = $this->em->getReference('\Shopware\Models\Order\Status', 18);

        $order->setPaymentStatus($paymentStatusInProgress);
        $order->setOrderStatus($orderStatusReserved);
        $this->em->flush($order);

        /** @var \Shopware\Models\Order\History[] $history */
        $history = $this->em->getRepository('\Shopware\Models\Order\History')->findBy(['order' => $order->getId()]);

        $this->assertCount(1, $history);

        $this->assertSame($paymentStatusInProgress, $history[0]->getPaymentStatus());
        $this->assertSame($previousPaymentStatus, $history[0]->getPreviousPaymentStatus());

        $this->assertSame($orderStatusReserved, $history[0]->getOrderStatus());
        $this->assertSame($previousOrderStatus, $history[0]->getPreviousOrderStatus());
    }

    public function createOrder()
    {
        $paymentStatusOpen = $this->em->getReference('\Shopware\Models\Order\Status', 17);
        $orderStatusOpen = $this->em->getReference('\Shopware\Models\Order\Status', 0);
        $paymentDebit = $this->em->getReference('\Shopware\Models\Payment\Payment', 2);
        $dispatchDefault = $this->em->getReference('\Shopware\Models\Dispatch\Dispatch', 9);
        $defaultShop = $this->em->getReference('\Shopware\Models\Shop\Shop', 1);

        $partner = new \Shopware\Models\Partner\Partner();
        $partner->setCompany('Dummy');
        $partner->setIdCode('Dummy');
        $partner->setDate(new \DateTime());
        $partner->setContact('Dummy');
        $partner->setStreet('Dummy');
        $partner->setZipCode('Dummy');
        $partner->setCity('Dummy');
        $partner->setPhone('Dummy');
        $partner->setFax('Dummy');
        $partner->setCountryName('Dummy');
        $partner->setEmail('Dummy');
        $partner->setWeb('Dummy');
        $partner->setProfile('Dummy')
        ;
        $this->em->persist($partner);

        $order = new \Shopware\Models\Order\Order();
        $order->setNumber('abc');
        $order->setPaymentStatus($paymentStatusOpen);
        $order->setOrderStatus($orderStatusOpen);
        $order->setPayment($paymentDebit);
        $order->setDispatch($dispatchDefault);
        $order->setPartner($partner);
        $order->setShop($defaultShop);
        $order->setInvoiceAmount(5);
        $order->setInvoiceAmountNet(5);
        $order->setInvoiceShipping(5);
        $order->setInvoiceShippingNet(5);
        $order->setTransactionId(5);
        $order->setComment('Dummy');
        $order->setCustomerComment('Dummy');
        $order->setInternalComment('Dummy');
        $order->setNet(true);
        $order->setTaxFree(false);
        $order->setTemporaryId(5);
        $order->setReferer('Dummy');
        $order->setTrackingCode('Dummy');
        $order->setLanguageIso('Dummy');
        $order->setCurrency('EUR');
        $order->setCurrencyFactor(5);
        $order->setRemoteAddress('127.0.0.1');

        return $order;
    }

    private function orderIsSaved($order)
    {
        $this->em->persist($order);
        $this->em->flush($order);
    }

    private function thenRetrieveHistoryOf($order)
    {
        return $this->em->getRepository('\Shopware\Models\Order\History')->findBy(['order' => $order->getId()]);
    }
}

class ZendAuthMock
{
    public function getIdentity()
    {
        return null;
    }
}
