<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Captcha;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Storefront\Framework\Captcha\AbstractCaptcha;
use Shopware\Storefront\Framework\Captcha\BasicCaptcha;
use Symfony\Component\HttpFoundation\Request;

class BasicCaptchaTest extends TestCase
{
    use KernelTestBehaviour;

    private const IS_VALID = true;
    private const IS_INVALID = false;
    private const BASIC_CAPTCHA_SESSION = 'kyln';

    /**
     * @var BasicCaptcha
     */
    private $captcha;

    public function setUp(): void
    {
        $this->captcha = $this->getContainer()->get(BasicCaptcha::class);
        $request = new Request();
        $request->setSession($this->getContainer()->get('session'));
        $this->getContainer()->get('request_stack')->push($request);

        $request->getSession()->set('basic_captcha_session', self::BASIC_CAPTCHA_SESSION);
    }

    public function testExtendsAbstractCaptcha(): void
    {
        static::assertInstanceOf(AbstractCaptcha::class, $this->captcha);
    }

    /**
     * @dataProvider requestDataProvider
     */
    public function testIsValid(Request $request, bool $shouldBeValid): void
    {
        if ($shouldBeValid) {
            static::assertTrue($this->captcha->isValid($request));
        } else {
            static::assertFalse($this->captcha->isValid($request));
        }
    }

    public function requestDataProvider(): array
    {
        return [
            [
                self::getRequest(),
                self::IS_INVALID,
            ],
            [
                self::getRequest([
                    BasicCaptcha::CAPTCHA_REQUEST_PARAMETER => null,
                ]),
                self::IS_INVALID,
            ],
            [
                self::getRequest([
                    BasicCaptcha::CAPTCHA_REQUEST_PARAMETER => '',
                ]),
                self::IS_INVALID,
            ],
            [
                self::getRequest([
                    BasicCaptcha::CAPTCHA_REQUEST_PARAMETER => 'notkyln',
                ]),
                self::IS_INVALID,
            ],
            [
                self::getRequest([
                    BasicCaptcha::CAPTCHA_REQUEST_PARAMETER => self::BASIC_CAPTCHA_SESSION,
                ]),
                self::IS_VALID,
            ],
        ];
    }

    private static function getRequest(array $data = []): Request
    {
        return new Request([], $data, [], [], [], [], []);
    }
}
