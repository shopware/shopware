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

namespace Shopware\Tests\Unit\Components;

use PHPUnit\Framework\TestCase;
use Shopware\Components\Validator\EmailValidator;

class EmailValidatorTest extends TestCase
{
    /**
     * @var EmailValidator
     */
    private $SUT;

    protected function setUp()
    {
        $this->SUT = new EmailValidator();
    }

    public function getValidEmails()
    {
        return [
            // old domains
            ['test@example.de'],
            ['test@example.com'],
            ['test@example.org'],

            // new released domains
            ['test@example.berlin'],
            ['test@example.email'],
            ['test@example.systems'],

            // new non released domains
            ['test@example.active'],
            ['test@example.love'],
            ['test@example.video'],
            ['test@example.app'],
            ['test@example.shop'],

            ['disposable.style.email.with+symbol@example.com'],
            ['other.email-with-dash@example.com'],

            // We will ignore quoted string local parts
            // this would blow up the simple regex method
            // array('"much.more unusual"@example.com'),
        ];
    }

    /**
     * @dataProvider getValidEmails
     *
     * @param string $email
     */
    public function testValidEmails($email)
    {
        $this->assertTrue($this->SUT->isValid($email));
    }

    public function getinvalidEmails()
    {
        return [
            ['test'],
            ['test@.de'],
            ['@example'],
            ['@example.de'],
            ['@.'],
            [' @foo.de'],
            ['@foo.'],
            ['foo@ .de'],
            ['foo@bar. '],
        ];
    }

    /**
     * @dataProvider getInvalidEmails
     *
     * @param string $email
     */
    public function testInvalidEmails($email)
    {
        $this->assertFalse($this->SUT->isValid($email));
    }
}
