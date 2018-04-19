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

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Shopware\Models\Customer\Customer;
use Shopware\Models\Customer\Group;

class LazyLoadingTest extends PHPUnit\Framework\TestCase
{
    /**
     * @var EntityManager
     */
    private $em;

    public static function tearDownAfterClass()
    {
        Shopware()->Db()->query("DELETE FROM s_user WHERE email LIKE 'lazyloadtest@shopware.com';");
        Shopware()->Db()->query("DELETE FROM s_user WHERE email LIKE 'lazyloadtest2@shopware.com';");
        Shopware()->Db()->query("DELETE FROM s_core_customergroups WHERE description LIKE 'testGroup'");
    }

    public function setUp()
    {
        $this->em = Shopware()->Models();
    }

    public function testCanCreateEntity()
    {
        $groupKey = $this->generateRandomString(5);

        $group = new Group();
        $group->setKey($groupKey);
        $group->setName('testGroup');
        $group->setTax(true);
        $group->setTaxInput(true);
        $group->setMode(1);

        $anotherGroup = new Group();
        $anotherGroup->setKey($this->generateRandomString(5));
        $anotherGroup->setName('testGroup');
        $anotherGroup->setTax(true);
        $anotherGroup->setTaxInput(true);
        $anotherGroup->setMode(1);

        $customer = new Customer();
        $customer->setEmail('lazyloadtest@shopware.com');
        $customer->setGroup($group);

        $this->em->persist($customer);
        $this->em->persist($group);
        $this->em->persist($anotherGroup);

        $this->em->flush();
        $this->em->clear();

        $this->assertNotEmpty($customer->getId());
        $this->assertNotEmpty($customer->getGroup()->getId());

        return $customer;
    }

    /**
     * @depends testCanCreateEntity
     */
    public function testLoadExplicit(Shopware\Models\Customer\Customer $customer)
    {
        $customerId = $customer->getId();
        $groupId = $customer->getGroup()->getId();
        $groupKey = $customer->getGroup()->getKey();
        $customer = null;
        $this->em->clear();

        /** @var Customer $customer */
        $customer = $this->em->getRepository('Shopware\Models\Customer\Customer')->find($customerId);

        /** @var Group $group */
        $group = $this->em->getRepository('Shopware\Models\Customer\Group')->find($groupId);

        $this->assertEquals($customer->getId(), $customerId);
        $this->assertEquals($customer->getGroupKey(), $groupKey);
        $this->assertEquals($group->getKey(), $groupKey);
        $this->assertEquals($customer->getGroupKey(), $group->getKey());
    }

    /**
     * @depends testCanCreateEntity
     */
    public function testDqlJoinQuery(Shopware\Models\Customer\Customer $customer)
    {
        $customerId = $customer->getId();
        $groupKey = $customer->getGroup()->getKey();
        $customer = null;
        $this->em->clear();

        $query = $this->em->createQuery("SELECT p, g FROM Shopware\Models\Customer\Customer p JOIN p.group g WHERE p.id = :customerId");
        $query->setParameter('customerId', $customerId);

        /** @var Customer $customer */
        $customer = $query->getOneOrNullResult();
        $group = $customer->getGroup();

        $this->assertEquals($customer->getId(), $customerId);
        $this->assertEquals($group->getKey(), $groupKey);
        $this->assertEquals($customer->getGroupKey(), $groupKey);
        $this->assertEquals($customer->getGroupKey(), $group->getKey());
    }

    /**
     * @depends testCanCreateEntity
     */
    public function testDqlFetchEagerQuery(Shopware\Models\Customer\Customer $customer)
    {
        $customerId = $customer->getId();
        $groupKey = $customer->getGroup()->getKey();
        $customer = null;
        $this->em->clear();

        $query = $this->em->createQuery("SELECT p FROM Shopware\Models\Customer\Customer p WHERE p.id = :customerId");
        $query->setFetchMode('Shopware\Models\Customer\Customer', 'group', ClassMetadata::FETCH_EAGER);
        $query->setParameter('customerId', $customerId);

        /** @var Customer $customer */
        $customer = $query->getOneOrNullResult();
        $group = $customer->getGroup();

        $this->assertEquals($customer->getId(), $customerId);
        $this->assertEquals($group->getKey(), $groupKey);
        $this->assertEquals($customer->getGroupKey(), $groupKey);
        $this->assertEquals($customer->getGroupKey(), $group->getKey());
    }

    /**
     * @depends testCanCreateEntity
     */
    public function testDqlLazyQuery(Shopware\Models\Customer\Customer $customer)
    {
        $customerId = $customer->getId();
        $groupKey = $customer->getGroup()->getKey();
        $customer = null;
        $this->em->clear();

        $query = $this->em->createQuery("SELECT p FROM Shopware\Models\Customer\Customer p WHERE p.id = :customerId");
        $query->setParameter('customerId', $customerId);

        /** @var Customer $customer */
        $customer = $query->getOneOrNullResult();
        $group = $customer->getGroup();

        $this->assertEquals($customer->getId(), $customerId);
        $this->assertEquals($group->getKey(), $groupKey);
        $this->assertEquals($customer->getGroupKey(), $groupKey);
        $this->assertEquals($customer->getGroupKey(), $group->getKey());
    }

    /**
     * @depends testCanCreateEntity
     */
    public function testLazyLoad(Shopware\Models\Customer\Customer $customer)
    {
        $customerId = $customer->getId();
        $groupKey = $customer->getGroup()->getKey();
        $customer = null;
        $this->em->clear();

        /** @var Customer $customer */
        $customer = $this->em->getRepository('Shopware\Models\Customer\Customer')->find($customerId);
        $group = $customer->getGroup();

        $this->assertEquals($customer->getId(), $customerId);
        $this->assertEquals($group->getKey(), $groupKey);
        $this->assertEquals($customer->getGroupKey(), $groupKey);
        $this->assertEquals($customer->getGroupKey(), $group->getKey());
    }

    /**
     * @depends testCanCreateEntity
     */
    public function testCanCreateEntityWithReference(Shopware\Models\Customer\Customer $customer)
    {
        $groupId = $customer->getGroup()->getId();
        $groupKey = $customer->getGroup()->getKey();
        $customer = null;
        $this->em->clear();

        $customer = new Customer();
        $customer->setGroup($this->em->getReference('Shopware\Models\Customer\Group', $groupId));

        $this->assertEmpty($customer->getId());
        $this->assertEquals($groupId, $customer->getGroup()->getId());
        $this->assertEquals($groupKey, $customer->getGroup()->getKey());

        return $customer;
    }

    public function testCanCreateEntityWithNewGroup()
    {
        $this->em->clear();
        $groupKey = $this->generateRandomString(5);
        $group = new Group();
        $group->setKey($groupKey);

        $customer = new Customer();
        $customer->setGroup($group);

        $this->assertEmpty($customer->getId());
        $this->assertEquals($group, $customer->getGroup());
        $this->assertEmpty($customer->getGroup()->getId());
        $this->assertEquals($groupKey, $customer->getGroup()->getKey());

        return $customer;
    }

    /**
     * @depends testCanCreateEntity
     */
    public function testCanUpdateEntityWithReference(Shopware\Models\Customer\Customer $customer)
    {
        $groupId = $customer->getGroup()->getId();
        $groupKey = $customer->getGroup()->getKey();

        $customer = new Customer();
        $customer->setEmail('lazyloadtest2@shopware.com');

        $this->em->persist($customer);
        $this->em->flush();
        $customerId = $customer->getId();

        $this->em->clear();

        $customer = $this->em->find('Shopware\Models\Customer\Customer', $customerId);
        $group = $this->em->getReference('Shopware\Models\Customer\Group', $groupId);
        $customer->setGroup($group);

        $this->assertNotEmpty($customer->getId());
        $this->assertEquals($group, $customer->getGroup());
        $this->assertNotEmpty($customer->getGroup()->getId());
        $this->assertEquals($groupKey, $customer->getGroup()->getKey());

        return $customer;
    }

    public function testOneToManyLoading()
    {
        $article = $this->em->find('Shopware\Models\Article\Supplier', 2);

        $this->assertNotEmpty(end($article->getArticles()->toArray())->getId());
    }

    /**
     * Test LazyLoading for:
     * - \Shopware\Models\Article\Notification::getArticleDetail()
     * - \Shopware\Models\Article\Notification::getCustomer()
     */
    public function testArticleNotifcation()
    {
        $conn = $this->em->getConnection();

        $ordernumber = $conn->fetchColumn('SELECT ordernumber FROM s_articles_details');
        $email = $conn->fetchColumn('SELECT email FROM s_user');

        $conn->insert('s_articles_notification', [
            'ordernumber' => $ordernumber,
            'mail' => $email,
        ]);

        $id = $conn->lastInsertId();

        /** @var \Shopware\Models\Article\Notification $notification */
        $notification = $this->em->getRepository('Shopware\Models\Article\Notification')->find($id);
        $this->assertEquals($ordernumber, $notification->getArticleDetail()->getNumber());
        $this->assertEquals($email, $notification->getCustomer()->getEmail());

        $conn->delete('s_articles_notification', ['id' => $id]);
    }

    /**
     * Test LazyLoading for:
     * - \Shopware\Models\Article\Price::getCustomerGroup()
     */
    public function testArticlePrice()
    {
        /** @var \Shopware\Models\Article\Price $price */
        $price = $this->em->getRepository('Shopware\Models\Article\Price')->findOneBy(['customerGroupKey' => 'ek']);
        $group = $price->getCustomerGroup();
        $this->assertEquals('EK', $group->getKey());
    }

    /**
     * Test LazyLoading for:
     * - \Shopware\Models\Article\Configurator\Template\Price::getCustomerGroup()
     */
    public function testTemplatePrice()
    {
        $conn = $this->em->getConnection();
        $conn->insert('s_article_configurator_template_prices', [
            'customer_group_key' => 'ek',
        ]);
        $id = $conn->lastInsertId();

        /** @var \Shopware\Models\Article\Configurator\Template\Price $templatePrice */
        $templatePrice = $this->em->getRepository('\Shopware\Models\Article\Configurator\Template\Price')->find($id);
        $this->assertEquals('EK', $templatePrice->getCustomerGroup()->getKey());

        $conn->delete('s_articles_notification', ['id' => $id]);
    }

    /**
     * Test LazyLoading for:
     * - \Shopware\Models\Newsletter\Address::getCustomer()
     */
    public function testNewsletterAddress()
    {
        $conn = $this->em->getConnection();
        $email = $conn->fetchColumn('SELECT email FROM s_user');
        $conn->insert('s_campaigns_mailaddresses', [
            'email' => $email,
        ]);
        $id = $conn->lastInsertId();

        /** @var \Shopware\Models\Newsletter\Address $address */
        $address = $this->em->getRepository('Shopware\Models\Newsletter\Address')->find($id);
        $this->assertEquals($email, $address->getCustomer()->getEmail());

        $conn->delete('s_campaigns_mailaddresses', ['id' => $id]);
    }

    /**
     * Test LazyLoading for:
     * - \Shopware\Models\Premium\Premium::getArticleDetail()
     */
    public function testPremium()
    {
        /** @var \Shopware\Models\Premium\Premium $premium */
        $premium = $this->em->getRepository('Shopware\Models\Premium\Premium')->find(1);
        $this->assertEquals('SW10209', $premium->getArticleDetail()->getNumber());
    }

    /**
     * Test LazyLoading for:
     * - \Shopware\Models\Newsletter\ContainerType\Article::getArticleDetail()
     */
    public function testArticleContainerType()
    {
        $conn = $this->em->getConnection();
        $ordernumber = $conn->fetchColumn('SELECT ordernumber FROM s_articles_details ORDER by id');
        $conn->insert('s_campaigns_articles', [
            'articleordernumber' => $ordernumber,
        ]);

        $id = $conn->lastInsertId();

        /** @var \Shopware\Models\Newsletter\ContainerType\Article $articleContainerType */
        $articleContainerType = $this->em->getRepository('Shopware\Models\Newsletter\ContainerType\Article')->find($id);
        $this->assertEquals($ordernumber, $articleContainerType->getArticleDetail()->getNumber());

        $conn->delete('s_campaigns_articles', ['id' => $id]);
    }

    private function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < $length; ++$i) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $randomString;
    }
}
