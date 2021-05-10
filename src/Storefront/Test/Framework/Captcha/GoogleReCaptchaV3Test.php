<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Captcha;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SystemConfigTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Captcha\AbstractCaptcha;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV3;
use Shopware\Storefront\Framework\Captcha\HoneypotCaptcha;
use Symfony\Component\HttpFoundation\Request;

class GoogleReCaptchaV3Test extends TestCase
{
    use KernelTestBehaviour;
    use SystemConfigTestBehaviour;

    private const IS_VALID = true;
    private const IS_INVALID = false;

    /**
     * @var HoneypotCaptcha
     */
    private $captcha;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function setUp(): void
    {
        $this->captcha = $this->getContainer()->get(GoogleReCaptchaV3::class);
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
    }

    protected function tearDown(): void
    {
        $this->systemConfigService->set('core.basicInformation.activeCaptchasV2', []);
    }

    public function testExtendsAbstractCaptcha(): void
    {
        static::assertInstanceOf(AbstractCaptcha::class, $this->captcha);
    }

    /**
     * @dataProvider requestDataSupportProvider
     */
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

        static::assertEquals($this->captcha->supports($request, $activeCaptchaConfig[$this->captcha->getName()]), $isSupported);
    }

    /**
     * @dataProvider requestDataIsValidProvider
     */
    public function testIsValid(Request $request, MockHandler $mockHandler, bool $shouldBeValid, ?string $secretKey = null, ?string $configThreshold = null): void
    {
        $handlerStack = HandlerStack::create($mockHandler);

        $client = new Client(['handler' => $handlerStack]);

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
        $captcha = new GoogleReCaptchaV3($client);

        static::assertEquals($captcha->isValid($request, $activeCaptchaConfig[$captcha->getName()]), $shouldBeValid);
    }

    public function requestDataIsValidProvider(): array
    {
        return [
            'request with no captcha input' => [
                self::getRequest(),
                new MockHandler(),
                self::IS_INVALID,
                'secret123',
            ],
            'request with null captcha input' => [
                self::getRequest([
                    GoogleReCaptchaV3::CAPTCHA_REQUEST_PARAMETER => null,
                ]),
                new MockHandler(),
                self::IS_INVALID,
                'secret123',
            ],
            'request with no secret key' => [
                self::getRequest([
                    GoogleReCaptchaV3::CAPTCHA_REQUEST_PARAMETER => 'something',
                ]),
                new MockHandler(),
                self::IS_INVALID,
                null,
            ],
            'request with empty secret key' => [
                self::getRequest([
                    GoogleReCaptchaV3::CAPTCHA_REQUEST_PARAMETER => 'something',
                ]),
                new MockHandler(),
                self::IS_INVALID,
                '',
            ],
            'request with request exception' => [
                self::getRequest([
                    GoogleReCaptchaV3::CAPTCHA_REQUEST_PARAMETER => 'something',
                ]),
                new MockHandler([
                    new RequestException('Error Communicating with Server', new GuzzleRequest('POST', 'test')),
                ]),
                self::IS_INVALID,
                'secret123',
            ],
            'request with server exception' => [
                self::getRequest([
                    GoogleReCaptchaV3::CAPTCHA_REQUEST_PARAMETER => 'something',
                ]),
                new MockHandler([
                    new ServerException('Server Exception', new GuzzleRequest('POST', 'test'), new Response()),
                ]),
                self::IS_INVALID,
                'secret123',
            ],
            'request with result false' => [
                self::getRequest([
                    GoogleReCaptchaV3::CAPTCHA_REQUEST_PARAMETER => 'something',
                ]),
                new MockHandler([
                    new Response(200, [], json_encode(['success' => false])),
                ]),
                self::IS_INVALID,
                'secret123',
            ],
            'request with no response' => [
                self::getRequest([
                    GoogleReCaptchaV3::CAPTCHA_REQUEST_PARAMETER => 'something',
                ]),
                new MockHandler([
                    new Response(200, [], null),
                ]),
                self::IS_INVALID,
                'secret123',
            ],
            'request with result true and score lower than default threshold' => [
                self::getRequest([
                    GoogleReCaptchaV3::CAPTCHA_REQUEST_PARAMETER => 'something',
                ]),
                new MockHandler([
                    new Response(200, [], json_encode(['success' => true, 'score' => '0.1'])),
                ]),
                self::IS_INVALID,
                'secret123',
            ],
            'request with result true and score higher default threshold' => [
                self::getRequest([
                    GoogleReCaptchaV3::CAPTCHA_REQUEST_PARAMETER => 'something',
                ]),
                new MockHandler([
                    new Response(200, [], json_encode(['success' => true, 'score' => '0.6'])),
                ]),
                self::IS_VALID,
                'secret123',
            ],
            'request with result true and score lower than config threshold' => [
                self::getRequest([
                    GoogleReCaptchaV3::CAPTCHA_REQUEST_PARAMETER => 'something',
                ]),
                new MockHandler([
                    new Response(200, [], json_encode(['success' => true, 'score' => '0.6'])),
                ]),
                self::IS_INVALID,
                'secret123',
                '0.7',
            ],
            'request with result true and score higher than config threshold' => [
                self::getRequest([
                    GoogleReCaptchaV3::CAPTCHA_REQUEST_PARAMETER => 'something',
                ]),
                new MockHandler([
                    new Response(200, [], json_encode(['success' => true, 'score' => '0.8'])),
                ]),
                self::IS_VALID,
                'secret123',
                '0.7',
            ],
        ];
    }

    public function requestDataSupportProvider(): array
    {
        return [
            'with get method and inactive captcha' => ['GET', false, false],
            'with get method and active captcha' => ['GET', true, false],
            'with post method and inactive captcha' => ['POST', false, false],
            'with post method and active captcha' => ['POST', true, true],
        ];
    }

    private static function getRequest(array $data = []): Request
    {
        return new Request([], $data, [], [], [], [], []);
    }
}
