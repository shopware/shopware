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

use Shopware\Components\Captcha\CaptchaValidator;
use Shopware\Components\Captcha\DefaultCaptcha;

class CaptchaValidatorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var DefaultCaptcha
     */
    private $captcha;

    public function setUp()
    {
        $this->captcha = new DefaultCaptcha(
            Shopware()->Container(),
            Shopware()->Container()->get('config'),
            Shopware()->Container()->get('template')
        );
    }

    public function testValidateCustomCaptchaHoneypot()
    {
        /** @var CaptchaValidator $validator */
        $validator = Shopware()->Container()->get('shopware.captcha.validator');
        $honeypotParams = include __DIR__ . '/fixtures/honeypotRequest.php';

        $request = new \Enlight_Controller_Request_RequestTestCase();
        $request->setParams($honeypotParams);

        $this->assertTrue($validator->validateByName($honeypotParams['captchaName'], $request));
    }

    public function testValidateCustomCaptchaDefault()
    {
        $this->captcha->getTemplateData();

        /** @var CaptchaValidator $validator */
        $validator = Shopware()->Container()->get('shopware.captcha.validator');
        $defaultParam = include __DIR__ . '/fixtures/honeypotRequest.php';
        $defaultParam['captchaName'] = 'default';

        $request = new \Enlight_Controller_Request_RequestTestCase();
        $request->setParams($defaultParam);

        $random = Shopware()->Session()->get(DefaultCaptcha::SESSION_KEY);
        $request->setParam('sCaptcha', array_pop(array_keys($random)));

        $this->assertTrue($validator->validateByName($defaultParam['captchaName'], $request));
    }

    public function testInvalidCaptcha()
    {
        $this->captcha->getTemplateData();

        /** @var CaptchaValidator $validator */
        $validator = Shopware()->Container()->get('shopware.captcha.validator');
        $defaultParam = include __DIR__ . '/fixtures/honeypotRequest.php';
        $defaultParam['captchaName'] = 'default';

        $request = new \Enlight_Controller_Request_RequestTestCase();
        $request->setParams($defaultParam);

        // set a random false parameter
        $request->setParam('sCaptcha', uniqid());

        $this->assertFalse($validator->validateByName($defaultParam['captchaName'], $request));
    }
}
