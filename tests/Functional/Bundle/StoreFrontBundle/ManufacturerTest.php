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

namespace Shopware\Tests\Functional\Bundle\StoreFrontBundle;

use Shopware\ProductManufacturer\Struct\ProductManufacturer;

class ManufacturerTest extends TestCase
{
    public function testManufacturerList()
    {
        $ids = [];
        $context = $this->getContext();

        $manufacturer = $this->helper->createManufacturer([
            'name' => 'testManufacturerList-1',
            'image' => 'ProductManufacturer-Cover-1',
            'link' => 'www.google.de?manufacturer=1',
            'metaTitle' => 'Meta title',
            'description' => 'Lorem ipsum manufacturer',
            'attribute' => ['id' => 100],
        ]);
        $ids[] = $manufacturer->getId();

        $manufacturer = $this->helper->createManufacturer([
            'name' => 'testManufacturerList-2',
            'image' => 'ProductManufacturer-Cover-2.jpg',
            'link' => 'www.google.de?manufacturer=2',
            'metaTitle' => 'Meta title',
            'description' => 'Lorem ipsum manufacturer',
            'attribute' => ['id' => 100],
        ]);
        $ids[] = $manufacturer->getId();

        $manufacturer = $this->helper->createManufacturer([
            'name' => 'testManufacturerList-2',
            'image' => 'ProductManufacturer-Cover-2.jpg',
            'link' => 'www.google.de?manufacturer=2',
            'metaTitle' => 'Meta title',
            'description' => 'Lorem ipsum manufacturer',
            'attribute' => ['id' => 100],
        ]);
        $ids[] = $manufacturer->getId();

        $manufacturers = Shopware()->Container()->get('storefront.manufacturer.service')
            ->getList($ids, $context);

        /** @var $manufacturer \Shopware\ProductManufacturer\Struct\ProductManufacturer */
        foreach ($manufacturers as $key => $manufacturer) {
            $this->assertEquals($key, $manufacturer->getId());

            $this->assertNotEmpty($manufacturer->getName());
            $this->assertNotEmpty($manufacturer->getLink());
            $this->assertNotEmpty($manufacturer->getDescription());
            $this->assertNotEmpty($manufacturer->getMetaTitle());
            $this->assertNotEmpty($manufacturer->getCoverFile());

            $this->assertGreaterThanOrEqual(1, $manufacturer->getAttributes());
            $this->assertTrue($manufacturer->hasAttribute('core'));
        }
    }
}
