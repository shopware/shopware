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

use Shopware\Models\Form\Form;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class Shopware_Tests_Models_FormTest extends Enlight_Components_Test_TestCase
{
    /**
     * @var array
     */
    public $testData = [
        'name' => 'Testform123',
        'text' => 'This is a Testform',
        'email' => 'max@mustermann.com',
        'emailTemplate' => 'Test Email Template',
        'emailSubject' => 'Test Email Subject',
        'text2' => 'Test Text2',
    ];
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
        $this->repo = Shopware()->Models()
                                ->getRepository('Shopware\Models\Form\Form');
    }

    /**
     * Tear down
     */
    protected function tearDown()
    {
        $form = $this->repo->findOneBy(['name' => 'Testform123']);

        if (!empty($form)) {
            $this->em->remove($form);
            $this->em->flush();
        }
        parent::tearDown();
    }

    /**
     * Test case
     */
    public function testGetterAndSetter()
    {
        $form = new Form();

        foreach ($this->testData as $field => $value) {
            $setMethod = 'set' . ucfirst($field);
            $getMethod = 'get' . ucfirst($field);

            $form->$setMethod($value);

            $this->assertEquals($form->$getMethod(), $value);
        }
    }

    /**
     * Test case
     */
    public function testFromArrayWorks()
    {
        $form = new Form();
        $form->fromArray($this->testData);

        foreach ($this->testData as $fieldname => $value) {
            $getMethod = 'get' . ucfirst($fieldname);
            $this->assertEquals($form->$getMethod(), $value);
        }
    }

    /**
     * Test case
     */
    public function testFormShouldBePersisted()
    {
        $form = new Form();
        $form->fromArray($this->testData);

        $this->em->persist($form);
        $this->em->flush();

        $formId = $form->getId();

        // remove form from entity manager
        $this->em->detach($form);
        unset($form);

        $form = $this->repo->find($formId);

        foreach ($this->testData as $fieldname => $value) {
            $getMethod = 'get' . ucfirst($fieldname);
            $this->assertEquals($form->$getMethod(), $value);
        }
    }
}
