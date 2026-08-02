<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Captcha;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Storefront\Framework\Captcha\BasicCaptcha;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('discovery')]
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

        static::assertSame($expected, $captcha->validate(new Request(request: $request), [])->count() === 0);
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the deprecated isValid() method
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testSubclassOverridingDeprecatedIsValidIsStillDispatched(): void
    {
        // Before validate() existed, isValid() was the only hook a plugin could use to tighten
        // (or loosen) a core captcha, so it has to keep deciding until it is removed in 6.8.
        $captcha = new class($this->createRequestStack(), static::createStub(SystemConfigService::class)) extends BasicCaptcha {
            public function isValid(Request $request, array $captchaConfig): bool
            {
                return $request->request->get('custom-check') === 'pass';
            }
        };

        // The submitted value matches the session, so the native check would let this through.
        static::assertCount(1, $captcha->runValidation(
            new Request(request: [BasicCaptcha::CAPTCHA_REQUEST_PARAMETER => 'valid-captcha-value', 'custom-check' => 'fail']),
            []
        ));
        static::assertCount(0, $captcha->runValidation(new Request(request: ['custom-check' => 'pass']), []));
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the deprecated getViolations() method
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testSubclassOverridingDeprecatedGetViolationsIsStillDispatched(): void
    {
        $captcha = new class($this->createRequestStack(), static::createStub(SystemConfigService::class)) extends BasicCaptcha {
            public function getViolations(): ConstraintViolationList
            {
                return new ConstraintViolationList([
                    new ConstraintViolation('', '', [], '', '', '', null, 'plugin-custom-code'),
                ]);
            }
        };

        // No captcha value at all, so the native check rejects and the violations are consulted.
        $violations = $captcha->runValidation(new Request(request: []), []);

        static::assertCount(1, $violations);
        $violation = $violations->get(0);
        static::assertInstanceOf(ConstraintViolation::class, $violation);
        // BasicCaptcha's own INVALID_CAPTCHA_CODE would mean the subclass was ignored.
        static::assertSame('plugin-custom-code', $violation->getCode());
        static::assertNotSame(BasicCaptcha::INVALID_CAPTCHA_CODE, $violation->getCode());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the deprecated isValid() method
     */
    public function testSubclassOverridingDeprecatedIsValidThrowsWhenFeatureIsActive(): void
    {
        // The deprecated pair is gone in 6.8, so a captcha still relying on it has to fail loudly
        // rather than have its check silently dropped.
        $captcha = new class($this->createRequestStack(), static::createStub(SystemConfigService::class)) extends BasicCaptcha {
            public function isValid(Request $request, array $captchaConfig): bool
            {
                return false;
            }
        };

        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: Overriding %s::isValid() is deprecated, implement validate() instead.',
            $captcha::class
        )));

        $captcha->runValidation(new Request(request: []), []);
    }

    #[TestDox('is not breaking and exposes its technical name')]
    public function testShouldBreakAndName(): void
    {
        $captcha = new BasicCaptcha(new RequestStack(), static::createStub(SystemConfigService::class));

        // The basic captcha is customer-solvable, so its failure is rendered, not a 403.
        static::assertFalse($captcha->shouldBreak());
        static::assertSame(BasicCaptcha::CAPTCHA_NAME, $captcha->getName());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the deprecated isValid()/getViolations() methods
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    #[TestDox('deprecated isValid() and getViolations() still work')]
    public function testDeprecatedMethods(): void
    {
        $requestStack = new RequestStack();
        $sessionRequest = new Request();
        $sessionRequest->setSession(new Session(new MockArraySessionStorage()));
        $requestStack->push($sessionRequest);
        $sessionRequest->getSession()->set('basic_captcha_session', 'valid-captcha-value');

        $captcha = new BasicCaptcha($requestStack, static::createStub(SystemConfigService::class));

        static::assertTrue($captcha->isValid(
            new Request(request: [BasicCaptcha::CAPTCHA_REQUEST_PARAMETER => 'valid-captcha-value']),
            []
        ));
        static::assertFalse($captcha->isValid(new Request(request: []), []));

        $violations = $captcha->getViolations();
        static::assertCount(1, $violations);
        $violation = $violations->get(0);
        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame(BasicCaptcha::INVALID_CAPTCHA_CODE, $violation->getCode());
        static::assertSame('/' . BasicCaptcha::CAPTCHA_REQUEST_PARAMETER, $violation->getPropertyPath());
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

    private function createRequestStack(string $captchaValue = 'valid-captcha-value'): RequestStack
    {
        $requestStack = new RequestStack();
        $sessionRequest = new Request();
        $sessionRequest->setSession(new Session(new MockArraySessionStorage()));
        $requestStack->push($sessionRequest);
        $sessionRequest->getSession()->set('basic_captcha_session', $captchaValue);

        return $requestStack;
    }
}
