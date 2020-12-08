<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Controller\CaptchaController;
use Shopware\Storefront\Framework\Captcha\AbstractCaptcha;

class CaptchaControllerTest extends TestCase
{
    private const CAPTCHA_NAME = 'lorem-ipsum';

    /**
     * @var CaptchaController
     */
    private $captchaController;

    protected function setUp(): void
    {
        $captchaMock = static::getMockBuilder(AbstractCaptcha::class)->getMock();
        $captchaMock->method('getName')->willReturn(self::CAPTCHA_NAME);

        $this->captchaController = new CaptchaController([$captchaMock]);
    }

    public function testList(): void
    {
        $expected = json_encode([
            self::CAPTCHA_NAME,
        ]);
        $response = $this->captchaController->list();

        static::assertJsonStringEqualsJsonString($expected, $response->getContent());
    }
}
