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
class Shopware_Tests_Models_Customer_BillingTest extends Enlight_Components_Test_TestCase
{
    public function testAddressFieldsLength()
    {
        $billing = $this->getRandomBilling();

        $billingId = $billing->getId();
        $originalStreet = $billing->getStreet();
        $originalZipCode = $billing->getZipCode();

        $billing->setStreet('This is a really really really long city name');
        $billing->setZipCode('This is a really really really long zip code');

        Shopware()->Models()->persist($billing);
        Shopware()->Models()->flush($billing);
        Shopware()->Models()->clear();

        $billing = Shopware()->Models()->getRepository('Shopware\Models\Customer\Billing')->find($billingId);
        $this->assertEquals('This is a really really really long city name', $billing->getStreet());
        $this->assertEquals('This is a really really really long zip code', $billing->getZipCode());

        $billing->setStreet($originalStreet);
        $billing->setZipCode($originalZipCode);

        Shopware()->Models()->persist($billing);
        Shopware()->Models()->flush($billing);
    }

    private function getRandomBilling()
    {
        $ids = Shopware()->Models()->getRepository('Shopware\Models\Customer\Billing')
            ->createQueryBuilder('b')
            ->select('b.id')
            ->getQuery()
            ->getArrayResult();

        shuffle($ids);

        return Shopware()->Models()->getRepository('Shopware\Models\Customer\Billing')->find(array_shift($ids));
    }
}
