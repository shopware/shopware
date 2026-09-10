<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Captcha;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Storefront\Framework\Captcha\HoneypotCaptcha;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
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
    public function testValidate(Request $request, bool $shouldBeValid): void
    {
        static::assertSame($shouldBeValid, $this->captcha->validate($request, [])->count() === 0);
    }

    public function testShouldBreakReturnsTrue(): void
    {
        // The honeypot is bot-only, so it must abort with a 403 rather than a visible error.
        static::assertTrue($this->captcha->shouldBreak());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the deprecated isValid() method
     */
    public function testDeprecatedIsValidStillValidates(): void
    {
        // DisabledFeatures is unit-namespace only, so the major flag is skipped explicitly.
        Feature::skipTestIfActive('v6.8.0.0', $this);

        static::assertTrue($this->captcha->isValid(self::getRequest(), []));
        static::assertFalse($this->captcha->isValid(
            self::getRequest([HoneypotCaptcha::CAPTCHA_REQUEST_PARAMETER => 'something']),
            []
        ));
    }

    /**
     * @return iterable<string, array{0: Request, 1: bool}>
     */
    public static function requestDataProvider(): iterable
    {
        yield 'request get request is valid' => [
            self::getRequest(),
            self::IS_VALID,
        ];
        yield 'GET request with custom captcha field is valid' => [
            self::getRequest([
                HoneypotCaptcha::CAPTCHA_REQUEST_PARAMETER => null,
            ]),
            self::IS_VALID,
        ];
        yield 'GET request with empty captcha field is invalid' => [
            self::getRequest([
                HoneypotCaptcha::CAPTCHA_REQUEST_PARAMETER => '',
            ]),
            self::IS_VALID,
        ];
        yield 'request get request is invalid' => [
            self::getRequest([
                HoneypotCaptcha::CAPTCHA_REQUEST_PARAMETER => 'something',
            ]),
            self::IS_INVALID,
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
