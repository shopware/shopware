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
use Shopware\Components\Password\Encoder\Sha256;

class Sha256Test extends TestCase
{
    /**
     * @var Sha256
     */
    private $hasher;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->hasher = new Sha256([
            'iterations' => 2,
            'salt_len' => 22,
        ]);
    }

    /**
     * Test case
     */
    public function testIsAvailable()
    {
        $this->assertInstanceOf(Sha256::class, $this->hasher);
    }

    /**
     * Test case
     */
    public function testGetNameShouldReturnName()
    {
        $this->assertEquals('Sha256', $this->hasher->getName());
    }

    /**
     * Test case
     */
    public function testGenerateShouldReturnString()
    {
        $this->assertInternalType('string', $this->hasher->encodePassword('foobar'));
    }

    /**
     * Test case
     */
    public function testGenerateShouldReturnDifferentHashesForSamePlaintextString()
    {
        $this->assertNotEquals($this->hasher->encodePassword('foobar'), $this->hasher->encodePassword('foobar'));
    }

    /**
     * Test case
     */
    public function testVerifyShouldReturnTrueForMatchingHash()
    {
        $hash = $this->hasher->encodePassword('foobar');

        $this->assertTrue($this->hasher->isPasswordValid('foobar', $hash));
    }

    /**
     * Test case
     */
    public function testVerifyShouldReturnFalseForNotMatchingHash()
    {
        $hash = $this->hasher->encodePassword('foobar');

        $this->assertFalse($this->hasher->isPasswordValid('notfoo', $hash));
    }

    /**
     * Test case
     */
    public function testRehash()
    {
        $hash = $this->hasher->encodePassword('foobar');

        $this->assertFalse($this->hasher->isReencodeNeeded($hash));
    }

    /**
     * Test case
     */
    public function testRehash2()
    {
        $hash = $this->hasher->encodePassword('foobar');
        $this->hasher = new Sha256([
            'iterations' => 3,
            'salt_len' => 22,
        ]);

        $this->assertTrue($this->hasher->isReencodeNeeded($hash));
    }
}
