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
use Shopware\Components\Plugin\XmlConfigDefinitionReader;

class XmlConfigDefinitionReaderTest extends TestCase
{
    /**
     * @var XmlConfigDefinitionReader
     */
    private $SUT;

    protected function setUp()
    {
        $this->SUT = new XmlConfigDefinitionReader();
    }

    public function testCanReadAndVerifyMinimalExample()
    {
        $result = $this->SUT->read(__DIR__ . '/examples/config_minimal.xml');
        $this->assertInternalType('array', $result);
    }

    public function testCanReadAndVerify()
    {
        $result = $this->SUT->read(__DIR__ . '/examples/config.xml');
        $this->assertInternalType('array', $result);
    }

    public function testCanReadStores()
    {
        $form = $this->SUT->read(__DIR__ . '/examples/config_store.xml');
        $this->assertInternalType('array', $form);

        $expected = [
            [
                '1',
                [
                    'de_DE' => 'DE 1',
                    'en_GB' => 'EN 1',
                ],
            ],
            [
                'TWO',
                [
                    'de_DE' => 'DE 2',
                    'en_GB' => 'EN 2',
                ],
            ],
            [
                '3',
                [
                    'en_GB' => 'Test',
                ],
            ],
            [
                '4',
                [
                    'en_GB' => 'Test default',
                    'de_DE' => 'Test',
                ],
            ],
        ];

        $this->assertEquals($expected, $form['elements'][0]['store']);
        $this->assertEquals('Shopware.apps.Base.store.Category', $form['elements'][1]['store']);
    }
}
