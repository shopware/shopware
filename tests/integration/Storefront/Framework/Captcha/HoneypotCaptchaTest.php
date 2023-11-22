<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Captcha;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
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
        $this->captcha = self::getContainer()->get(HoneypotCaptcha::class);
    }

    #[DataProvider('requestDataProvider')]
    public function testIsValid(Request $request, bool $shouldBeValid): void
    {
        if ($shouldBeValid) {
            static::assertTrue($this->captcha->isValid($request, []));
        } else {
            static::assertFalse($this->captcha->isValid($request, []));
        }
    }

    /**
     * @return list<array{0: Request, 1: bool}>
     */
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

    /**
     * @param array<string, mixed> $data
     */
    private static function getRequest(array $data = []): Request
    {
        return new Request(request: $data);
    }
}
