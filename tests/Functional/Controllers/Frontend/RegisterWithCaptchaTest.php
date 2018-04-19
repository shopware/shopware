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

use Shopware\Components\Captcha\DefaultCaptcha;

class Shopware_Tests_Controllers_Frontend_RegisterTest extends Enlight_Components_Test_Plugin_TestCase
{
    public function testValidateCaptchaIsUninstalled()
    {
        $postParameter = include __DIR__ . '/fixtures/captchaRequest.php';
        $this->Request()->setHeader('User-Agent', include __DIR__ . '/fixtures/UserAgent.php');
        $this->Request()->setMethod('POST');
        $this->Request()->setPost($postParameter);

        $this->dispatch('/register/saveRegister/sTarget/account/sTargetAction/index');

        $viewVariables = $this->View()->getAssign();

        $this->assertTrue($this->Response()->isRedirect());
        $this->assertArrayNotHasKey('errors', $viewVariables);
    }

    public function testNoCaptcha()
    {
        $postParameter = include __DIR__ . '/fixtures/captchaRequest.php';
        $postParameter['captchaName'] = 'nocaptcha';

        $this->Request()->setHeader('User-Agent', include __DIR__ . '/fixtures/UserAgent.php');
        $this->Request()->setMethod('POST');
        $this->Request()->setPost($postParameter);

        $this->dispatch('/register/saveRegister/sTarget/account/sTargetAction/index');

        $viewVariables = $this->View()->getAssign();

        $this->assertTrue($this->Response()->isRedirect());
        $this->assertArrayNotHasKey('errors', $viewVariables);
    }

    public function testHoneypot()
    {
        $postParameter = include __DIR__ . '/fixtures/captchaRequest.php';
        $postParameter['captchaName'] = 'honeypot';

        $this->Request()->setHeader('User-Agent', include __DIR__ . '/fixtures/UserAgent.php');
        $this->Request()->setMethod('POST');
        $this->Request()->setPost($postParameter);

        $this->dispatch('/register/saveRegister/sTarget/account/sTargetAction/index');

        $viewVariables = $this->View()->getAssign();

        $this->assertTrue($this->Response()->isRedirect());
        $this->assertArrayNotHasKey('errors', $viewVariables);
    }

    public function testDefault()
    {
        $captchaName = 'default';
        $random = md5(uniqid());
        $sessionVars = ['sCaptcha' => $random, $random => true];

        Shopware()->Session()->offsetSet(DefaultCaptcha::SESSION_KEY, $sessionVars);

        $postParameter = include __DIR__ . '/fixtures/captchaRequest.php';
        $postParameter['captchaName'] = $captchaName;
        $postParameter['sCaptcha'] = $random;

        $this->Request()->setHeader('User-Agent', include __DIR__ . '/fixtures/UserAgent.php');
        $this->Request()->setMethod('POST');
        $this->Request()->setPost($postParameter);

        $this->dispatch('/register/saveRegister/sTarget/account/sTargetAction/index');

        $viewVariables = $this->View()->getAssign();

        $this->assertTrue($this->Response()->isRedirect());
        $this->assertArrayNotHasKey('errors', $viewVariables);
    }

    public function testInvalidHoneypot()
    {
        $postParameter = include __DIR__ . '/fixtures/captchaRequest.php';
        $postParameter['captchaName'] = 'honeypot';
        $postParameter['first_name_confirmation'] = uniqid();

        $this->Request()->setHeader('User-Agent', include __DIR__ . '/fixtures/UserAgent.php');
        $this->Request()->setMethod('POST');
        $this->Request()->setPost($postParameter);

        $this->dispatch('/register/saveRegister/sTarget/account/sTargetAction/index');

        $viewVariables = $this->View()->getAssign();

        $this->assertArrayHasKey('errors', $viewVariables);
    }

    public function testInvalidDefault()
    {
        $captchaName = 'default';
        $random = md5(uniqid());
        $sessionVars = ['sCaptcha' => $random];

        Shopware()->Session()->offsetSet(DefaultCaptcha::SESSION_KEY, $sessionVars);

        $postParameter = include __DIR__ . '/fixtures/captchaRequest.php';
        $postParameter['captchaName'] = $captchaName;
        $postParameter['sCaptcha'] = $random;

        $this->Request()->setHeader('User-Agent', include __DIR__ . '/fixtures/UserAgent.php');
        $this->Request()->setMethod('POST');
        $this->Request()->setPost($postParameter);

        $this->dispatch('/register/saveRegister/sTarget/account/sTargetAction/index');

        $viewVariables = $this->View()->getAssign();

        $this->assertArrayHasKey('errors', $viewVariables);
    }
}
