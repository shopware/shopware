<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Sso\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\Config\LoginConfig;
use Shopware\Core\Framework\Sso\Config\LoginConfigService;
use Shopware\Core\Framework\Sso\SsoException;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LoginConfigService::class)]
class LoginConfigServiceTest extends TestCase
{
    public function testGetConfigWithEmptyRawConfig(): void
    {
        $configService = $this->createLoginConfigService([]);

        $config = $configService->getConfig();

        static::assertNull($config);
    }

    public function testGetConfigWithValidRawConfig(): void
    {
        $rawConfig = [
            'use_default' => true,
            'client_id' => 'clientId',
            'client_secret' => 'clientSecret',
            'redirect_uri' => 'http://redirect.url',
            'base_url' => 'http://base.url',
            'authorize_path' => '/authorize',
            'token_path' => '/token',
            'jwks_path' => '/jwks.json',
            'scope' => 'scope',
            'register_url' => 'http://register.url',
        ];

        $configService = $this->createLoginConfigService($rawConfig);

        $config = $configService->getConfig();

        static::assertNotNull($config);
        static::assertSame($rawConfig['use_default'], $config->useDefault);
        static::assertSame($rawConfig['client_id'], $config->clientId);
        static::assertSame($rawConfig['client_secret'], $config->clientSecret);
        static::assertSame($rawConfig['redirect_uri'], $config->redirectUri);
        static::assertSame($rawConfig['base_url'], $config->baseUrl);
        static::assertSame($rawConfig['authorize_path'], $config->authorizePath);
        static::assertSame($rawConfig['token_path'], $config->tokenPath);
    }

    /**
     * @param array{use_default: bool, client_id: non-empty-string, client_secret: non-empty-string, redirect_uri: non-empty-string, base_url: non-empty-string, authorize_path: non-empty-string, token_path: non-empty-string, jwks_path: non-empty-string, scope: non-empty-string, register_url: non-empty-string} $rawConfig
     */
    #[DataProvider('getConfigErrorsTestDataProvider')]
    public function testGetConfigErrors(array $rawConfig, string $exceptionMessage): void
    {
        $configService = $this->createLoginConfigService($rawConfig);

        $this->expectExceptionObject(new SsoException(0, '0', $exceptionMessage));

        $configService->getConfig();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getConfigErrorsTestDataProvider(): array
    {
        return [
            'use_default is not set' => [
                'rawConfig' => self::createConfig([], ['use_default']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [use_default] is missing',
            ],

            'use_default is null' => [
                'rawConfig' => self::createConfig(['use_default' => null]),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [use_default] is null',
            ],

            'use_default is not a bool' => [
                'rawConfig' => self::createConfig(['use_default' => 'asd']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [use_default] is not a boolean',
            ],

            'client_id is not set' => [
                'rawConfig' => self::createConfig([], ['client_id']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_id] is missing',
            ],

            'client_id is null' => [
                'rawConfig' => self::createConfig(['client_id' => null]),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_id] is null, [client_id] is blank',
            ],

            'client_id is blank' => [
                'rawConfig' => self::createConfig(['client_id' => '']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_id] is blank',
            ],

            'client_id is no a string' => [
                'rawConfig' => self::createConfig(['client_id' => 12]),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_id] is invalid string',
            ],

            'client_secret is not set' => [
                'rawConfig' => self::createConfig([], ['client_secret']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_secret] is missing',
            ],

            'client_secret is null' => [
                'rawConfig' => self::createConfig(['client_secret' => null]),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_secret] is null, [client_secret] is blank',
            ],

            'client_secret is blank' => [
                'rawConfig' => self::createConfig(['client_secret' => '']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_secret] is blank',
            ],

            'client_secret is no a string' => [
                'rawConfig' => self::createConfig(['client_secret' => 12]),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_secret] is invalid string',
            ],

            'redirect_uri is not set' => [
                'rawConfig' => self::createConfig([], ['redirect_uri']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [redirect_uri] is missing',
            ],

            'redirect_uri is null' => [
                'rawConfig' => self::createConfig(['redirect_uri' => null]),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [redirect_uri] is null, [redirect_uri] is blank',
            ],

            'redirect_uri is blank' => [
                'rawConfig' => self::createConfig(['redirect_uri' => '']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [redirect_uri] is blank',
            ],

            'redirect_uri is no a string' => [
                'rawConfig' => self::createConfig(['redirect_uri' => 12]),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [redirect_uri] is invalid string, [redirect_uri] is invalid URL',
            ],

            'redirect_uri is no a url' => [
                'rawConfig' => self::createConfig(['redirect_uri' => 'redirectUri']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [redirect_uri] is invalid URL',
            ],

            'base_url is not set' => [
                'rawConfig' => self::createConfig([], ['base_url']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [base_url] is missing',
            ],

            'base_url is null' => [
                'rawConfig' => self::createConfig(['base_url' => null]),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [base_url] is null, [base_url] is blank',
            ],

            'base_url is blank' => [
                'rawConfig' => self::createConfig(['base_url' => '']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [base_url] is blank',
            ],

            'base_url is not a string' => [
                'rawConfig' => self::createConfig(['base_url' => 12]),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [base_url] is invalid string, [base_url] is invalid URL',
            ],

            'base_url is no a url' => [
                'rawConfig' => self::createConfig(['base_url' => 'baseUrl']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [base_url] is invalid URL',
            ],

            'base_url ends with slash' => [
                'rawConfig' => self::createConfig(['base_url' => 'http://base.url/']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [base_url] should not end with "/"',
            ],

            'authorize_path is null' => [
                'rawConfig' => self::createConfig([], ['authorize_path']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [authorize_path] is missing',
            ],

            'authorize_path is blank' => [
                'rawConfig' => self::createConfig(['authorize_path' => '']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [authorize_path] is blank',
            ],

            'authorize_path is not a string' => [
                'rawConfig' => self::createConfig(['authorize_path' => 12]),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [authorize_path] is invalid string, [authorize_path] is invalid path. Requires to start with "/"',
            ],

            'authorize_path not start with slash' => [
                'rawConfig' => self::createConfig(['authorize_path' => 'http://authorize']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [authorize_path] is invalid path. Requires to start with "/"',
            ],

            'token_path is null' => [
                'rawConfig' => self::createConfig([], ['token_path']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [token_path] is missing',
            ],

            'token_path is blank' => [
                'rawConfig' => self::createConfig(['token_path' => '']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [token_path] is blank',
            ],

            'token_path is not a string' => [
                'rawConfig' => self::createConfig(['token_path' => 12]),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [token_path] is invalid string, [token_path] is invalid path. Requires to start with "/"',
            ],

            'token_path not start with slash' => [
                'rawConfig' => self::createConfig(['token_path' => 'any/token']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [token_path] is invalid path. Requires to start with "/"',
            ],

            'jwks_path is null' => [
                'rawConfig' => self::createConfig([], ['jwks_path']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [jwks_path] is missing',
            ],

            'jwks_path is blank' => [
                'rawConfig' => self::createConfig(['jwks_path' => '']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [jwks_path] is blank',
            ],

            'jwks_path is not a string' => [
                'rawConfig' => self::createConfig(['jwks_path' => 12]),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [jwks_path] is invalid string, [jwks_path] is invalid path. Requires to start with "/"',
            ],

            'jwks_path not start with slash' => [
                'rawConfig' => self::createConfig(['jwks_path' => 'jwks/json']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [jwks_path] is invalid path. Requires to start with "/"',
            ],

            'scope is null' => [
                'rawConfig' => self::createConfig([], ['scope']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [scope] is missing',
            ],

            'scope is blank' => [
                'rawConfig' => self::createConfig(['scope' => '']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [scope] is blank',
            ],

            'scope is not a string' => [
                'rawConfig' => self::createConfig(['scope' => 12]),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [scope] is invalid string',
            ],

            'register_url is null' => [
                'rawConfig' => self::createConfig([], ['register_url']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [register_url] is missing',
            ],

            'register_url is empty' => [
                'rawConfig' => self::createConfig(['register_url' => '']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [register_url] is blank',
            ],

            'register_url is not valid url' => [
                'rawConfig' => self::createConfig(['register_url' => 'registerUrl']),
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [register_url] is invalid URL',
            ],
        ];
    }

    public function testCreateTemplateDataWithNullAsLoginConfig(): void
    {
        $configService = $this->createLoginConfigService([]);

        $result = $configService->createTemplateData('randomString');

        static::assertTrue($result->useDefault);
        static::assertNull($result->url);
    }

    public function testCreateTemplateDataWithValidLoginConfig(): void
    {
        $rawConfig = [
            'use_default' => false,
            'client_id' => 'clientId',
            'client_secret' => 'clientSecret',
            'redirect_uri' => 'http://redirect.url',
            'base_url' => 'http://base.url',
            'authorize_path' => '/authorize',
            'token_path' => '/token',
            'jwks_path' => '/jwks.json',
            'scope' => 'scope',
            'register_url' => 'http://register.url',
        ];

        $configService = $this->createLoginConfigService($rawConfig);

        $result = $configService->createTemplateData('randomString');

        static::assertFalse($result->useDefault);
        static::assertSame('oauth.sso.auth?rdm=randomString', $result->url);
    }

    /**
     * @param array<string, string|bool> $rawConfig
     */
    #[DataProvider('createRedirectUrlTestDataProvider')]
    public function testCreateRedirectUrl(string $random, array $rawConfig, string $expectedUrl): void
    {
        $configService = $this->createLoginConfigService($rawConfig);
        $loginConfig = $configService->getConfig();
        static::assertInstanceOf(LoginConfig::class, $loginConfig);

        $result = $configService->createRedirectUrl($random);
        static::assertStringStartsWith($loginConfig->baseUrl, $result);

        // check query parameter
        $query = $this->getQueryParamsAsArray($result);
        static::assertSame($loginConfig->clientId, $query['client_id']);
        static::assertSame($loginConfig->redirectUri, $query['redirect_uri']);

        static::assertIsString($query['state']);

        // check state and query parameter
        static::assertArrayHasKey('state', $query);

        static::assertStringStartsWith('api.oauth.sso.code', $query['state']);

        $stateUrlQuery = $this->getQueryParamsAsArray($query['state']);
        static::assertSame($random, $stateUrlQuery['rdm']);

        // check given expected url
        static::assertSame($expectedUrl, $result);
    }

    /**
     * @return array<string, array{random: string, rawConfig: array<string, string|bool>, expectedUrl: string}>
     */
    public static function createRedirectUrlTestDataProvider(): array
    {
        return [
            'Test case one' => [
                'random' => 'justARandomString',
                'rawConfig' => [
                    'use_default' => true,
                    'client_id' => 'justAClientID',
                    'client_secret' => 'justAClientSecret',
                    'redirect_uri' => 'http://justARedirectUri.org',
                    'base_url' => 'http://justABaseUrl.net',
                    'authorize_path' => '/authorize',
                    'token_path' => '/token',
                    'jwks_path' => '/jwks.json',
                    'scope' => 'scope',
                    'register_url' => 'http://register.url',
                ],
                'expectedUrl' => 'http://justABaseUrl.net/authorize?client_id=justAClientID&redirect_uri=http%3A%2F%2FjustARedirectUri.org&response_type=code&scope=scope&state=api.oauth.sso.code%3Frdm%3DjustARandomString',
            ],

            'Test case two' => [
                'random' => 'justARandomString',
                'rawConfig' => [
                    'use_default' => true,
                    'client_id' => 'anotherClientID',
                    'client_secret' => 'anotherClientSecret',
                    'redirect_uri' => 'http://another-redirect-url.org',
                    'base_url' => 'http://another-base-url.net',
                    'authorize_path' => '/authorize',
                    'token_path' => '/token',
                    'jwks_path' => '/jwks.json',
                    'scope' => 'scope',
                    'register_url' => 'http://register.url',
                ],
                'expectedUrl' => 'http://another-base-url.net/authorize?client_id=anotherClientID&redirect_uri=http%3A%2F%2Fanother-redirect-url.org&response_type=code&scope=scope&state=api.oauth.sso.code%3Frdm%3DjustARandomString',
            ],
        ];
    }

    /**
     * @param array<string, string|bool> $rawConfig
     */
    public function createLoginConfigService(array $rawConfig): LoginConfigService
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(function ($name, $parameter) {
            return $name . '?' . \http_build_query($parameter);
        });

        // @phpstan-ignore argument.type (The LoginConfigService parameter1 expects a specific array which we were unable to provide for testing purposes)
        return new LoginConfigService($rawConfig, $router);
    }

    /**
     * @param array<string, mixed> $apply
     * @param array<int, string> $unset
     *
     * @return array<string, mixed>
     */
    private static function createConfig(array $apply, array $unset = []): array
    {
        $defaultConfig = [
            'use_default' => true,
            'client_id' => 'clientId',
            'client_secret' => 'clientSecret',
            'redirect_uri' => 'http://redirect.url',
            'base_url' => 'http://base.url',
            'authorize_path' => '/authorize',
            'token_path' => '/token',
            'jwks_path' => '/jwks.json',
            'scope' => 'scope',
            'register_url' => 'http://register.url',
        ];

        foreach ($unset as $key) {
            unset($defaultConfig[$key]);
        }

        return array_merge($defaultConfig, $apply);
    }

    /**
     * @return array<int|string, array<mixed>|string>
     */
    private function getQueryParamsAsArray(string $url): array
    {
        $urlResult = \parse_url($url);
        $query = [];
        static::assertIsArray($urlResult);
        static::assertArrayHasKey('query', $urlResult);
        \parse_str($urlResult['query'], $query);

        return $query;
    }
}
