<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Captcha;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Storefront\Framework\Captcha\AbstractCaptcha;
use Shopware\Storefront\Framework\Captcha\HoneypotCaptcha;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class HoneypotCaptchaTest extends TestCase
{
    use KernelTestBehaviour;

    private const IS_VALID = true;
    private const IS_INVALID = false;

    private HoneypotCaptcha $captcha;

    protected function setUp(): void
    {
        $this->captcha = $this->getContainer()->get(HoneypotCaptcha::class);
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
            static::assertTrue($this->captcha->isValid($request, []));
        } else {
            static::assertFalse($this->captcha->isValid($request, []));
        }
    }

    public static function requestDataProvider(): array
    {
        return [
            [
                self::getRequest(),
                self::IS_VALID,
            ],
            [
                self::getRequest([
                    HoneypotCaptcha::CAPTCHA_REQUEST_PARAMETER => null,
                ]),
                self::IS_VALID,
            ],
            [
                self::getRequest([
                    HoneypotCaptcha::CAPTCHA_REQUEST_PARAMETER => '',
                ]),
                self::IS_VALID,
            ],
            [
                self::getRequest([
                    HoneypotCaptcha::CAPTCHA_REQUEST_PARAMETER => 'something',
                ]),
                self::IS_INVALID,
            ],
        ];
    }

    private static function getRequest(array $data = []): Request
    {
        return new Request([], $data, [], [], [], [], []);
    }
}
