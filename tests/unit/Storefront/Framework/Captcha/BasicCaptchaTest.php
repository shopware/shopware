<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Captcha;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
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
    /**
     * @param array<string, string|null> $request
     */
    #[DataProvider('validatesProvider')]
    #[TestDox('rejects invalid or missing captcha values and accepts a matching one')]
    public function testRejectsInvalidAndAcceptsMatchingCaptchaValues(array $request, bool $expected): void
    {
        $requestStack = new RequestStack();
        $sessionRequest = new Request();
        $sessionRequest->setSession(new Session(new MockArraySessionStorage()));
        $requestStack->push($sessionRequest);
        $sessionRequest->getSession()->set('basic_captcha_session', 'valid-captcha-value');

        $captcha = new BasicCaptcha($requestStack, static::createStub(SystemConfigService::class));

        static::assertSame($expected, $captcha->isValid(new Request(request: $request), []));
    }

    #[DataProvider('supportsProvider')]
    #[TestDox('supports only POST requests when captcha is active in config')]
    public function testSupportsOnlyPostRequests(mixed $configValue, Request $request, bool $expected): void
    {
        $systemConfigService = new StaticSystemConfigService([
            'core.basicInformation.activeCaptchasV2' => $configValue,
        ]);

        $captcha = new BasicCaptcha(new RequestStack(), $systemConfigService);

        static::assertSame($expected, $captcha->supports($request, []));
    }

    #[TestDox('passes the sales channel ID from the request context to the config lookup')]
    public function testSupportsUsesContextSalesChannelId(): void
    {
        $salesChannelId = 'test-sales-channel-id';
        $active = [BasicCaptcha::CAPTCHA_NAME => ['isActive' => true]];

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())
            ->method('get')
            ->with('core.basicInformation.activeCaptchasV2', $salesChannelId)
            ->willReturn($active);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn($salesChannelId);

        $request = Request::create('/', 'POST');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);

        $captcha = new BasicCaptcha(new RequestStack(), $systemConfigService);

        static::assertTrue($captcha->supports($request, []));
    }

    /**
     * @return \Generator<string, array{configValue: mixed, request: Request, expected: bool}>
     */
    public static function supportsProvider(): \Generator
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

    /**
     * @return \Generator<string, array{request: array<string, string|null>, expected: bool}>
     */
    public static function validatesProvider(): \Generator
    {
        yield 'missing captcha parameter' => [
            'request' => [],
            'expected' => false,
        ];
        yield 'invalid captcha value' => [
            'request' => [BasicCaptcha::CAPTCHA_REQUEST_PARAMETER => 'invalid-captcha-value'],
            'expected' => false,
        ];
        yield 'valid captcha value' => [
            'request' => [BasicCaptcha::CAPTCHA_REQUEST_PARAMETER => 'valid-captcha-value'],
            'expected' => true,
        ];
    }
}
