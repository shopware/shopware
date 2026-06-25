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
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Storefront\Framework\Captcha\CaptchaException;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV3;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * @internal
 */
#[CoversClass(GoogleReCaptchaV3::class)]
class GoogleReCaptchaV3Test extends TestCase
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
            GoogleReCaptchaV3::CAPTCHA_NAME => [
                'name' => GoogleReCaptchaV3::CAPTCHA_NAME,
                'isActive' => $isActive,
            ],
        ]);

        $activeCaptchaConfig = $this->systemConfigService->get('core.basicInformation.activeCaptchasV2');
        static::assertIsArray($activeCaptchaConfig);
        $captcha = $this->getCaptcha();

        static::assertSame($captcha->supports($request, $activeCaptchaConfig[$captcha->getName()]), $isSupported);
    }

    #[DataProvider('requestDataIsValidProvider')]
    public function testIsValid(Request $request, MockHandler $mockHandler, bool $shouldBeValid, ?string $secretKey = null, ?string $configThreshold = null): void
    {
        $this->systemConfigService->set('core.basicInformation.activeCaptchasV2', [
            GoogleReCaptchaV3::CAPTCHA_NAME => [
                'name' => GoogleReCaptchaV3::CAPTCHA_NAME,
                'isActive' => true,
                'config' => [
                    'secretKey' => $secretKey,
                    'thresholdScore' => $configThreshold,
                ],
            ],
        ]);

        $activeCaptchaConfig = $this->systemConfigService->get('core.basicInformation.activeCaptchasV2');
        static::assertIsArray($activeCaptchaConfig);
        $captcha = $this->getCaptcha($mockHandler);

        static::assertSame($captcha->isValid($request, $activeCaptchaConfig[$captcha->getName()]), $shouldBeValid);
    }

    /**
     * @return iterable<string, array{0: Request, 1: MockHandler, 2: bool, 3: string|null, 4?: string}>
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
                GoogleReCaptchaV3::CAPTCHA_REQUEST_PARAMETER => null,
            ]),
            new MockHandler(),
            self::IS_INVALID,
            'secret123',
        ];
        yield 'request with no secret key' => [
            self::getRequest([
                GoogleReCaptchaV3::CAPTCHA_REQUEST_PARAMETER => 'something',
            ]),
            new MockHandler(),
            self::IS_INVALID,
            null,
        ];
        yield 'request with empty secret key' => [
            self::getRequest([
                GoogleReCaptchaV3::CAPTCHA_REQUEST_PARAMETER => 'something',
            ]),
            new MockHandler(),
            self::IS_INVALID,
            '',
        ];
        yield 'request with request exception' => [
            self::getRequest([
                GoogleReCaptchaV3::CAPTCHA_REQUEST_PARAMETER => 'something',
            ]),
            new MockHandler([
                new RequestException('Error Communicating with Server', new GuzzleRequest('POST', 'test')),
            ]),
            self::IS_INVALID,
            'secret123',
        ];
        yield 'request with server exception' => [
            self::getRequest([
                GoogleReCaptchaV3::CAPTCHA_REQUEST_PARAMETER => 'something',
            ]),
            new MockHandler([
                new ServerException('Server Exception', new GuzzleRequest('POST', 'test'), new Response()),
            ]),
            self::IS_INVALID,
            'secret123',
        ];
        yield 'request with result false' => [
            self::getRequest([
                GoogleReCaptchaV3::CAPTCHA_REQUEST_PARAMETER => 'something',
            ]),
            new MockHandler([
                new Response(200, [], json_encode(['success' => false], \JSON_THROW_ON_ERROR)),
            ]),
            self::IS_INVALID,
            'secret123',
        ];
        yield 'request with no response' => [
            self::getRequest([
                GoogleReCaptchaV3::CAPTCHA_REQUEST_PARAMETER => 'something',
            ]),
            new MockHandler([
                new Response(200, [], null),
            ]),
            self::IS_INVALID,
            'secret123',
        ];
        yield 'request with result true and score lower than default threshold' => [
            self::getRequest([
                GoogleReCaptchaV3::CAPTCHA_REQUEST_PARAMETER => 'something',
            ]),
            new MockHandler([
                new Response(200, [], json_encode(['success' => true, 'score' => '0.1'], \JSON_THROW_ON_ERROR)),
            ]),
            self::IS_INVALID,
            'secret123',
        ];
        yield 'request with result true and score higher default threshold' => [
            self::getRequest([
                GoogleReCaptchaV3::CAPTCHA_REQUEST_PARAMETER => 'something',
            ]),
            new MockHandler([
                new Response(200, [], json_encode(['success' => true, 'score' => '0.6'], \JSON_THROW_ON_ERROR)),
            ]),
            self::IS_VALID,
            'secret123',
        ];
        yield 'request with result true and score lower than config threshold' => [
            self::getRequest([
                GoogleReCaptchaV3::CAPTCHA_REQUEST_PARAMETER => 'something',
            ]),
            new MockHandler([
                new Response(200, [], json_encode(['success' => true, 'score' => '0.6'], \JSON_THROW_ON_ERROR)),
            ]),
            self::IS_INVALID,
            'secret123',
            '0.7',
        ];
        yield 'request with result true and score higher than config threshold' => [
            self::getRequest([
                GoogleReCaptchaV3::CAPTCHA_REQUEST_PARAMETER => 'something',
            ]),
            new MockHandler([
                new Response(200, [], json_encode(['success' => true, 'score' => '0.8'], \JSON_THROW_ON_ERROR)),
            ]),
            self::IS_VALID,
            'secret123',
            '0.7',
        ];
    }

    public function testMissingTokenExposesCookieRequiredViolation(): void
    {
        $captcha = $this->getCaptcha();

        static::assertFalse($captcha->isValid(self::getRequest(), $this->getCaptchaConfig()));

        $violations = $captcha->getViolations();
        static::assertCount(1, $violations);

        $violation = $violations->get(0);
        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame(GoogleReCaptchaV3::COOKIE_REQUIRED_VIOLATION, $violation->getCode());
        static::assertSame('', $violation->getPropertyPath());
    }

    public function testFailedVerificationExposesGenericViolation(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['success' => true, 'score' => '0.1'], \JSON_THROW_ON_ERROR)),
        ]);
        $captcha = $this->getCaptcha($mockHandler);

        static::assertFalse($captcha->isValid(
            self::getRequest([GoogleReCaptchaV3::CAPTCHA_REQUEST_PARAMETER => 'token']),
            $this->getCaptchaConfig()
        ));

        // Present-but-invalid token (score below threshold) -> generic captcha violation.
        $violations = $captcha->getViolations();
        static::assertCount(1, $violations);

        $violation = $violations->get(0);
        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame(CaptchaException::INVALID_CAPTCHA_ERROR, $violation->getCode());
    }

    public function testViolationsAreResetBetweenChecks(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['success' => true, 'score' => '0.9'], \JSON_THROW_ON_ERROR)),
        ]);
        $captcha = $this->getCaptcha($mockHandler);

        static::assertFalse($captcha->isValid(self::getRequest(), $this->getCaptchaConfig()));
        static::assertCount(1, $captcha->getViolations());

        static::assertTrue($captcha->isValid(
            self::getRequest([GoogleReCaptchaV3::CAPTCHA_REQUEST_PARAMETER => 'token']),
            $this->getCaptchaConfig()
        ));
        static::assertCount(0, $captcha->getViolations());
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
     * Returns the active captcha config in the same shape as the system config service,
     * so the value passed to {@see GoogleReCaptchaV3::isValid()} keeps a `mixed` type.
     */
    private function getCaptchaConfig(string $secretKey = 'secret123'): mixed
    {
        $this->systemConfigService->set('core.basicInformation.activeCaptchasV2', [
            GoogleReCaptchaV3::CAPTCHA_NAME => [
                'name' => GoogleReCaptchaV3::CAPTCHA_NAME,
                'isActive' => true,
                'config' => ['secretKey' => $secretKey],
            ],
        ]);

        $config = $this->systemConfigService->get('core.basicInformation.activeCaptchasV2');
        static::assertIsArray($config);

        return $config[GoogleReCaptchaV3::CAPTCHA_NAME];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function getRequest(array $data = []): Request
    {
        return new Request(request: $data);
    }

    private function getCaptcha(?MockHandler $mockHandler = null): GoogleReCaptchaV3
    {
        return new GoogleReCaptchaV3(
            new Client([
                'handler' => HandlerStack::create($mockHandler ?? new MockHandler()),
            ])
        );
    }
}
