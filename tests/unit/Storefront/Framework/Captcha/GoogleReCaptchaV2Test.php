<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Captcha;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Storefront\Framework\Captcha\CaptchaException;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV2;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(GoogleReCaptchaV2::class)]
class GoogleReCaptchaV2Test extends TestCase
{
    private const IS_VALID = true;
    private const IS_INVALID = false;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->systemConfigService = new StaticSystemConfigService();
    }

    protected function tearDown(): void
    {
        $this->systemConfigService->set('core.basicInformation.activeCaptchasV2', []);
    }

    #[DataProvider('requestDataSupportProvider')]
    public function testIsSupported(string $method, bool $isActive, bool $isSupported): void
    {
        $request = self::getRequest();
        $request->setMethod($method);

        $this->systemConfigService->set('core.basicInformation.activeCaptchasV2', [
            GoogleReCaptchaV2::CAPTCHA_NAME => [
                'name' => GoogleReCaptchaV2::CAPTCHA_NAME,
                'isActive' => $isActive,
            ],
        ]);

        $activeCaptchaConfig = $this->systemConfigService->get('core.basicInformation.activeCaptchasV2');
        static::assertIsArray($activeCaptchaConfig);
        $captcha = $this->getCaptcha();

        static::assertSame($captcha->supports($request, $activeCaptchaConfig[$captcha->getName()]), $isSupported);
    }

    #[DataProvider('requestDataIsValidProvider')]
    public function testValidate(Request $request, MockHandler $mockHandler, bool $shouldBeValid, ?string $secretKey): void
    {
        $this->systemConfigService->set('core.basicInformation.activeCaptchasV2', [
            GoogleReCaptchaV2::CAPTCHA_NAME => [
                'name' => GoogleReCaptchaV2::CAPTCHA_NAME,
                'isActive' => true,
                'config' => [
                    'secretKey' => $secretKey,
                ],
            ],
        ]);

        $activeCaptchaConfig = $this->systemConfigService->get('core.basicInformation.activeCaptchasV2');
        static::assertIsArray($activeCaptchaConfig);
        $captcha = $this->getCaptcha($mockHandler);

        static::assertSame($captcha->validate($request, $activeCaptchaConfig[$captcha->getName()])->count() === 0, $shouldBeValid);
    }

    /**
     * @return iterable<string, array{0: Request, 1: MockHandler, 2: bool, 3: string|null}>
     */
    public static function requestDataIsValidProvider(): iterable
    {
        yield 'request with no captcha input' => [
            self::getRequest(),
            new MockHandler(),
            self::IS_INVALID,
            'secret123',
        ];
        yield 'request with null captcha input' => [
            self::getRequest([
                GoogleReCaptchaV2::CAPTCHA_REQUEST_PARAMETER => null,
            ]),
            new MockHandler(),
            self::IS_INVALID,
            'secret123',
        ];
        yield 'request with no secret key' => [
            self::getRequest([
                GoogleReCaptchaV2::CAPTCHA_REQUEST_PARAMETER => 'something',
            ]),
            new MockHandler(),
            self::IS_INVALID,
            null,
        ];
        yield 'request with empty secret key' => [
            self::getRequest([
                GoogleReCaptchaV2::CAPTCHA_REQUEST_PARAMETER => 'something',
            ]),
            new MockHandler(),
            self::IS_INVALID,
            '',
        ];
        yield 'request with request exception' => [
            self::getRequest([
                GoogleReCaptchaV2::CAPTCHA_REQUEST_PARAMETER => 'something',
            ]),
            new MockHandler([
                new RequestException('Error Communicating with Server', new GuzzleRequest('POST', 'test')),
            ]),
            self::IS_INVALID,
            'secret123',
        ];
        yield 'request with server exception' => [
            self::getRequest([
                GoogleReCaptchaV2::CAPTCHA_REQUEST_PARAMETER => 'something',
            ]),
            new MockHandler([
                new ServerException('Server Exception', new GuzzleRequest('POST', 'test'), new Response()),
            ]),
            self::IS_INVALID,
            'secret123',
        ];
        yield 'request with result false' => [
            self::getRequest([
                GoogleReCaptchaV2::CAPTCHA_REQUEST_PARAMETER => 'something',
            ]),
            new MockHandler([
                new Response(200, [], json_encode(['success' => false], \JSON_THROW_ON_ERROR)),
            ]),
            self::IS_INVALID,
            'secret123',
        ];
        yield 'request with no response' => [
            self::getRequest([
                GoogleReCaptchaV2::CAPTCHA_REQUEST_PARAMETER => 'something',
            ]),
            new MockHandler([
                new Response(200, [], null),
            ]),
            self::IS_INVALID,
            'secret123',
        ];
        yield 'request with result true' => [
            self::getRequest([
                GoogleReCaptchaV2::CAPTCHA_REQUEST_PARAMETER => 'something',
            ]),
            new MockHandler([
                new Response(200, [], json_encode(['success' => true], \JSON_THROW_ON_ERROR)),
            ]),
            self::IS_VALID,
            'secret123',
        ];
    }

    public function testMissingTokenExposesTokenRequiredViolation(): void
    {
        $captcha = $this->getCaptcha();

        $violations = $captcha->validate(self::getRequest(), $this->getCaptchaConfig());
        static::assertCount(1, $violations);

        $violation = $violations->get(0);
        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame(CaptchaException::RECAPTCHA_TOKEN_REQUIRED_VIOLATION, $violation->getCode());
        static::assertSame('', $violation->getPropertyPath());
    }

    public function testFailedVerificationExposesGenericViolation(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['success' => false], \JSON_THROW_ON_ERROR)),
        ]);
        $captcha = $this->getCaptcha($mockHandler);

        // Present-but-invalid token -> generic captcha violation.
        $violations = $captcha->validate(
            self::getRequest([GoogleReCaptchaV2::CAPTCHA_REQUEST_PARAMETER => 'token']),
            $this->getCaptchaConfig()
        );
        static::assertCount(1, $violations);

        $violation = $violations->get(0);
        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame(CaptchaException::INVALID_CAPTCHA_ERROR, $violation->getCode());
    }

    public function testShouldBreakReturnsFalse(): void
    {
        // reCAPTCHA failures always carry customer-facing violations, so they must be
        // shown to the customer instead of breaking the request with a 403.
        static::assertFalse($this->getCaptcha()->shouldBreak());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the deprecated isValid() method
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDeprecatedIsValidStillValidates(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['success' => true], \JSON_THROW_ON_ERROR)),
        ]);
        $captcha = $this->getCaptcha($mockHandler);

        static::assertFalse($captcha->isValid(self::getRequest(), $this->getCaptchaConfig()));
        static::assertTrue($captcha->isValid(
            self::getRequest([GoogleReCaptchaV2::CAPTCHA_REQUEST_PARAMETER => 'token']),
            $this->getCaptchaConfig()
        ));
    }

    /**
     * @return iterable<string, array{0: string, 1: bool, 2: bool}>
     */
    public static function requestDataSupportProvider(): iterable
    {
        yield 'with get method and inactive captcha' => ['GET', false, false];
        yield 'with get method and active captcha' => ['GET', true, false];
        yield 'with post method and inactive captcha' => ['POST', false, false];
        yield 'with post method and active captcha' => ['POST', true, true];
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the deprecated isValid() method
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testSubclassOverridingDeprecatedIsValidIsStillDispatched(): void
    {
        // Before validate() existed, isValid() was the only hook a plugin could use to tighten
        // (or loosen) a core captcha, so it has to keep deciding until it is removed in 6.8.
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['success' => true], \JSON_THROW_ON_ERROR)),
        ]);
        $captcha = new class($this->getClient($mockHandler)) extends GoogleReCaptchaV2 {
            public function isValid(Request $request, array $captchaConfig): bool
            {
                return $request->request->get('custom-check') === 'pass';
            }
        };

        // Google accepts the token, but the subclass rejects the request.
        static::assertCount(1, $captcha->validate(
            self::getRequest([GoogleReCaptchaV2::CAPTCHA_REQUEST_PARAMETER => 'token', 'custom-check' => 'fail']),
            $this->getCaptchaConfig()
        ));

        // ... and the other way round: no token at all, which the native path would reject.
        static::assertCount(0, $captcha->validate(
            self::getRequest(['custom-check' => 'pass']),
            $this->getCaptchaConfig()
        ));
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the deprecated getViolations() method
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testSubclassOverridingDeprecatedGetViolationsIsStillDispatched(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['success' => false], \JSON_THROW_ON_ERROR)),
        ]);
        $captcha = new class($this->getClient($mockHandler)) extends GoogleReCaptchaV2 {
            public function getViolations(): ConstraintViolationList
            {
                return new ConstraintViolationList([
                    new ConstraintViolation('', '', [], '', '', '', null, 'plugin-custom-code'),
                ]);
            }
        };

        $violations = $captcha->validate(
            self::getRequest([GoogleReCaptchaV2::CAPTCHA_REQUEST_PARAMETER => 'token']),
            $this->getCaptchaConfig()
        );

        static::assertCount(1, $violations);
        $violation = $violations->get(0);
        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame('plugin-custom-code', $violation->getCode());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the deprecated isValid() method
     */
    public function testSubclassOverridingDeprecatedIsValidThrowsWhenFeatureIsActive(): void
    {
        // The deprecated pair is gone in 6.8, so a captcha still relying on it has to fail loudly
        // rather than have its check silently dropped.
        $captcha = new class($this->getClient()) extends GoogleReCaptchaV2 {
            public function isValid(Request $request, array $captchaConfig): bool
            {
                return false;
            }
        };

        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: Overriding %s::isValid() is deprecated, implement validate() instead.',
            $captcha::class
        )));

        $captcha->validate(self::getRequest(), $this->getCaptchaConfig());
    }

    /**
     * @return array<string, mixed>
     */
    private function getCaptchaConfig(string $secretKey = 'secret123'): array
    {
        return [
            'name' => GoogleReCaptchaV2::CAPTCHA_NAME,
            'isActive' => true,
            'config' => ['secretKey' => $secretKey],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function getRequest(array $data = []): Request
    {
        return new Request(request: $data);
    }

    private function getCaptcha(?MockHandler $mockHandler = null): GoogleReCaptchaV2
    {
        return new GoogleReCaptchaV2($this->getClient($mockHandler));
    }

    private function getClient(?MockHandler $mockHandler = null): Client
    {
        return new Client([
            'handler' => HandlerStack::create($mockHandler ?? new MockHandler()),
        ]);
    }
}
