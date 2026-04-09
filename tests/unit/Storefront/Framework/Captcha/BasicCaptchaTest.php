<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Captcha;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Captcha\BasicCaptcha;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * @internal
 */
#[CoversClass(BasicCaptcha::class)]
class BasicCaptchaTest extends TestCase
{
    private BasicCaptcha $captcha;

    protected function setUp(): void
    {
        $requestStack = new RequestStack();
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack->push($request);
        $request->getSession()->set('basic_captcha_session', 'valid-captcha-value');

        $this->captcha = new BasicCaptcha($requestStack, $this->createMock(SystemConfigService::class));
    }

    #[DataProvider('requestDataProvider')]
    public function testIsValid(Request $request, bool $expected): void
    {
        static::assertSame($expected, $this->captcha->isValid($request, []));
    }

    /**
     * @return \Generator<string, array{request: Request, expected: bool}>
     */
    public static function requestDataProvider(): \Generator
    {
        yield 'missing captcha parameter' => [
            'request' => self::getRequest(),
            'expected' => false,
        ];
        yield 'null captcha parameter' => [
            'request' => self::getRequest([BasicCaptcha::CAPTCHA_REQUEST_PARAMETER => null]),
            'expected' => false,
        ];
        yield 'empty string captcha parameter' => [
            'request' => self::getRequest([BasicCaptcha::CAPTCHA_REQUEST_PARAMETER => '']),
            'expected' => false,
        ];
        yield 'invalid captcha value' => [
            'request' => self::getRequest([BasicCaptcha::CAPTCHA_REQUEST_PARAMETER => 'invalid-captcha-value']),
            'expected' => false,
        ];
        yield 'valid captcha value' => [
            'request' => self::getRequest([BasicCaptcha::CAPTCHA_REQUEST_PARAMETER => 'valid-captcha-value']), // defined in setUp method
            'expected' => true,
        ];
    }

    #[DataProvider('supportsDataProvider')]
    public function testSupports(mixed $configValue, Request $request, bool $expected): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('get')->willReturn($configValue);

        $captcha = new BasicCaptcha(new RequestStack(), $systemConfigService);

        static::assertSame($expected, $captcha->supports($request, []));
    }

    /**
     * @return \Generator<string, array{configValue: mixed, request: Request, expected: bool}>
     */
    public static function supportsDataProvider(): \Generator
    {
        $active = [BasicCaptcha::CAPTCHA_NAME => ['isActive' => true]];

        yield 'config null returns false' => [
            'configValue' => null,
            'request' => Request::create('/', 'POST'),
            'expected' => false,
        ];
        yield 'config empty array returns false' => [
            'configValue' => [],
            'request' => Request::create('/', 'POST'),
            'expected' => false,
        ];
        yield 'GET request returns false' => [
            'configValue' => $active,
            'request' => Request::create('/', 'GET'),
            'expected' => false,
        ];
        yield 'captcha not in config returns false' => [
            'configValue' => ['other' => ['isActive' => true]],
            'request' => Request::create('/', 'POST'),
            'expected' => false,
        ];
        yield 'captcha inactive returns false' => [
            'configValue' => [BasicCaptcha::CAPTCHA_NAME => ['isActive' => false]],
            'request' => Request::create('/', 'POST'),
            'expected' => false,
        ];
        yield 'POST with active captcha returns true' => [
            'configValue' => $active,
            'request' => Request::create('/', 'POST'),
            'expected' => true,
        ];
    }

    public function testSupportsUsesContextSalesChannelId(): void
    {
        $salesChannelId = 'test-sales-channel-id';
        $active = [BasicCaptcha::CAPTCHA_NAME => ['isActive' => true]];

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())
            ->method('get')
            ->with('core.basicInformation.activeCaptchasV2', $salesChannelId)
            ->willReturn($active);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn($salesChannelId);

        $request = Request::create('/', 'POST');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);

        $captcha = new BasicCaptcha(new RequestStack(), $systemConfigService);

        static::assertTrue($captcha->supports($request, []));
    }

    /**
     * @param array<string, string|null> $data
     */
    private static function getRequest(array $data = []): Request
    {
        return new Request([], $data, [], [], [], [], null);
    }
}
