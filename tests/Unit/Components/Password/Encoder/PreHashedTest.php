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

namespace Shopware\Tests\Unit\Components\Password\Encoder;

use PHPUnit\Framework\TestCase;
use Shopware\Components\Password\Encoder\PreHashed;

class PreHashedTest extends TestCase
{
    /**
     * @var PreHashed
     */
    private $hasher;

    public function setUp()
    {
        $this->hasher = new PreHashed();
    }

    /**
     * Test case
     */
    public function testGetNameShouldReturnName()
    {
        $this->assertEquals('PreHashed', $this->hasher->getName());
    }

    public function testEncodePasswordShouldNotModifyInput()
    {
        $this->assertEquals('example', $this->hasher->encodePassword('example'));
    }

    public function testRehash()
    {
        $this->assertFalse($this->hasher->isReencodeNeeded('example'));
    }

    public function testValidatePasswordForSameHashes()
    {
        $this->assertTrue($this->hasher->isPasswordValid('example', 'example'));
    }

    public function testValidatePasswordForDifferentHashes()
    {
        $this->assertFalse($this->hasher->isPasswordValid('example', 'alice'));
    }
}
