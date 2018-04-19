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

namespace Shopware\Tests\Unit\Components\Plugin;

use PHPUnit\Framework\TestCase;
use Shopware\Components\Plugin\XmlMenuReader;

class XmlMenuReaderTest extends TestCase
{
    /**
     * @var XmlMenuReader
     */
    private $SUT;

    protected function setUp()
    {
        $this->SUT = new XmlMenuReader();
    }

    public function testCanReadAndVerifyMinimal()
    {
        $result = $this->SUT->read(__DIR__ . '/examples/menu_minimal.xml');
        $this->assertInternalType('array', $result);
    }

    public function testCanReadAndVerify()
    {
        $result = $this->SUT->read(__DIR__ . '/examples/menu.xml');
        $this->assertInternalType('array', $result);
    }

    public function testCanReadMenuWithRootEntry()
    {
        $result = $this->SUT->read(__DIR__ . '/examples/menu_root_entry.xml');
        $this->assertInternalType('array', $result);
        $this->assertTrue($result[0]['isRootMenu']);
    }
}
