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

namespace Shopware\Tests\Functional\Components;

use Shopware\Components\Captcha\DefaultCaptcha;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.com)
 */
class DefaultCaptchaTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var DefaultCaptcha
     */
    private $captcha;

    public function setUp()
    {
        if (!function_exists('imagettftext')) {
            $this->markTestSkipped(
                'The imagettftext() function is not available.'
            );
        }

        $this->captcha = new DefaultCaptcha(
            Shopware()->Container(),
            Shopware()->Container()->get('config'),
            Shopware()->Container()->get('template')
        );
    }

    public function testCaptchaIsInitiallyInvalid()
    {
        $request = new \Enlight_Controller_Request_RequestTestCase();
        $request->setParam('sCaptcha', 'foobar');
        $this->assertFalse($this->captcha->validate($request));
    }

    public function testValidCaptcha()
    {
        $templateData = $this->captcha->getTemplateData();
        $this->assertArrayHasKey('img', $templateData);

        $random = Shopware()->Session()->get(DefaultCaptcha::SESSION_KEY);

        $request = new \Enlight_Controller_Request_RequestTestCase();
        $request->setParam('sCaptcha', array_pop(array_keys($random)));

        $this->assertTrue($this->captcha->validate($request));
    }

    public function testValidMultipleCaptchaCalls()
    {
        // call captcha five times
        $this->captcha->getTemplateData();
        $this->captcha->getTemplateData();
        $this->captcha->getTemplateData();
        $this->captcha->getTemplateData();
        $templateData = $this->captcha->getTemplateData();

        $this->assertArrayHasKey('img', $templateData);

        $random = Shopware()->Session()->get(DefaultCaptcha::SESSION_KEY);
        $this->assertCount(5, $random);

        $request = new \Enlight_Controller_Request_RequestTestCase();
        $request->setParam('sCaptcha', 'INVALID CHALLENGE');
        $this->assertFalse($this->captcha->validate($request));

        $random = Shopware()->Session()->get(DefaultCaptcha::SESSION_KEY);
        $this->assertCount(5, $random, 'Invalid captcha should not decrease captcha backlog');

        // extract second generated captcha
        $challenge = array_slice(array_keys($random), 1, 1)[0];
        $request = new \Enlight_Controller_Request_RequestTestCase();
        $request->setParam('sCaptcha', $challenge);
        $this->assertTrue($this->captcha->validate($request));

        $random = Shopware()->Session()->get(DefaultCaptcha::SESSION_KEY);
        $this->assertCount(4, $random, 'Valid challenge should decrease captcha backlog');
    }
}
