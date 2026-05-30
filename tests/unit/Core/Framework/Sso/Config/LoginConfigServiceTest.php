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
        $config = $this->createLoginConfigService([])->getConfig();

        static::assertNull($config);
    }

    public function testGetConfigWithValidRawConfig(): void
    {
        $rawConfig = [
            'use_default' => true,
            'client_id' => 'clientId',
            'client_secret' => 'clientSecret',
            'redirect_uri' => 'https://redirect.url',
            'base_url' => 'https://base.url',
            'authorize_path' => '/authorize',
            'token_path' => '/token',
            'jwks_path' => '/jwks.json',
            'scope' => 'scope',
            'register_url' => 'https://register.url',
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
     * @param array<string, mixed> $rawConfig
     */
    #[DataProvider('getConfigErrorsTestDataProvider')]
    public function testGetConfigErrors(array $rawConfig, string $exceptionMessage): void
    {
        $configService = $this->createLoginConfigService($rawConfig);

        $this->expectExceptionObject(new SsoException(0, '0', $exceptionMessage));

        $configService->getConfig();
    }

    /**
     * @return iterable<string, array<string, mixed>>
     */
    public static function getConfigErrorsTestDataProvider(): iterable
    {
        yield 'use_default is not set' => [
            'rawConfig' => self::createConfig([], ['use_default']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [use_default] is missing',
        ];
        yield 'use_default is null' => [
            'rawConfig' => self::createConfig(['use_default' => null]),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [use_default] is null',
        ];
        yield 'use_default is not a bool' => [
            'rawConfig' => self::createConfig(['use_default' => 'asd']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [use_default] is not a boolean',
        ];
        yield 'client_id is not set' => [
            'rawConfig' => self::createConfig([], ['client_id']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_id] is missing',
        ];
        yield 'client_id is null' => [
            'rawConfig' => self::createConfig(['client_id' => null]),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_id] is null, [client_id] is blank',
        ];
        yield 'client_id is blank' => [
            'rawConfig' => self::createConfig(['client_id' => '']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_id] is blank',
        ];
        yield 'client_id is no a string' => [
            'rawConfig' => self::createConfig(['client_id' => 12]),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_id] is invalid string',
        ];
        yield 'client_secret is not set' => [
            'rawConfig' => self::createConfig([], ['client_secret']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_secret] is missing',
        ];
        yield 'client_secret is null' => [
            'rawConfig' => self::createConfig(['client_secret' => null]),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_secret] is null, [client_secret] is blank',
        ];
        yield 'client_secret is blank' => [
            'rawConfig' => self::createConfig(['client_secret' => '']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_secret] is blank',
        ];
        yield 'client_secret is no a string' => [
            'rawConfig' => self::createConfig(['client_secret' => 12]),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_secret] is invalid string',
        ];
        yield 'redirect_uri is not set' => [
            'rawConfig' => self::createConfig([], ['redirect_uri']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [redirect_uri] is missing',
        ];
        yield 'redirect_uri is null' => [
            'rawConfig' => self::createConfig(['redirect_uri' => null]),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [redirect_uri] is null, [redirect_uri] is blank',
        ];
        yield 'redirect_uri is blank' => [
            'rawConfig' => self::createConfig(['redirect_uri' => '']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [redirect_uri] is blank',
        ];
        yield 'redirect_uri is no a string' => [
            'rawConfig' => self::createConfig(['redirect_uri' => 12]),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [redirect_uri] is invalid string, [redirect_uri] is invalid URL',
        ];
        yield 'redirect_uri is no a url' => [
            'rawConfig' => self::createConfig(['redirect_uri' => 'redirectUri']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [redirect_uri] is invalid URL',
        ];
        yield 'base_url is not set' => [
            'rawConfig' => self::createConfig([], ['base_url']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [base_url] is missing',
        ];
        yield 'base_url is null' => [
            'rawConfig' => self::createConfig(['base_url' => null]),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [base_url] is null, [base_url] is blank',
        ];
        yield 'base_url is blank' => [
            'rawConfig' => self::createConfig(['base_url' => '']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [base_url] is blank',
        ];
        yield 'base_url is not a string' => [
            'rawConfig' => self::createConfig(['base_url' => 12]),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [base_url] is invalid string, [base_url] is invalid URL',
        ];
        yield 'base_url is no a url' => [
            'rawConfig' => self::createConfig(['base_url' => 'baseUrl']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [base_url] is invalid URL',
        ];
        yield 'base_url ends with slash' => [
            'rawConfig' => self::createConfig(['base_url' => 'https://base.url/']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [base_url] should not end with "/"',
        ];
        yield 'authorize_path is null' => [
            'rawConfig' => self::createConfig([], ['authorize_path']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [authorize_path] is missing',
        ];
        yield 'authorize_path is blank' => [
            'rawConfig' => self::createConfig(['authorize_path' => '']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [authorize_path] is blank',
        ];
        yield 'authorize_path is not a string' => [
            'rawConfig' => self::createConfig(['authorize_path' => 12]),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [authorize_path] is invalid string, [authorize_path] is invalid path. Requires to start with "/"',
        ];
        yield 'authorize_path not start with slash' => [
            'rawConfig' => self::createConfig(['authorize_path' => 'https://authorize']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [authorize_path] is invalid path. Requires to start with "/"',
        ];
        yield 'token_path is null' => [
            'rawConfig' => self::createConfig([], ['token_path']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [token_path] is missing',
        ];
        yield 'token_path is blank' => [
            'rawConfig' => self::createConfig(['token_path' => '']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [token_path] is blank',
        ];
        yield 'token_path is not a string' => [
            'rawConfig' => self::createConfig(['token_path' => 12]),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [token_path] is invalid string, [token_path] is invalid path. Requires to start with "/"',
        ];
        yield 'token_path not start with slash' => [
            'rawConfig' => self::createConfig(['token_path' => 'any/token']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [token_path] is invalid path. Requires to start with "/"',
        ];
        yield 'jwks_path is null' => [
            'rawConfig' => self::createConfig([], ['jwks_path']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [jwks_path] is missing',
        ];
        yield 'jwks_path is blank' => [
            'rawConfig' => self::createConfig(['jwks_path' => '']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [jwks_path] is blank',
        ];
        yield 'jwks_path is not a string' => [
            'rawConfig' => self::createConfig(['jwks_path' => 12]),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [jwks_path] is invalid string, [jwks_path] is invalid path. Requires to start with "/"',
        ];
        yield 'jwks_path not start with slash' => [
            'rawConfig' => self::createConfig(['jwks_path' => 'jwks/json']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [jwks_path] is invalid path. Requires to start with "/"',
        ];
        yield 'scope is null' => [
            'rawConfig' => self::createConfig([], ['scope']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [scope] is missing',
        ];
        yield 'scope is blank' => [
            'rawConfig' => self::createConfig(['scope' => '']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [scope] is blank',
        ];
        yield 'scope is not a string' => [
            'rawConfig' => self::createConfig(['scope' => 12]),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [scope] is invalid string',
        ];
        yield 'register_url is null' => [
            'rawConfig' => self::createConfig([], ['register_url']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [register_url] is missing',
        ];
        yield 'register_url is empty' => [
            'rawConfig' => self::createConfig(['register_url' => '']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [register_url] is blank',
        ];
        yield 'register_url is not valid url' => [
            'rawConfig' => self::createConfig(['register_url' => 'registerUrl']),
            'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [register_url] is invalid URL',
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
            'redirect_uri' => 'https://redirect.url',
            'base_url' => 'https://base.url',
            'authorize_path' => '/authorize',
            'token_path' => '/token',
            'jwks_path' => '/jwks.json',
            'scope' => 'scope',
            'register_url' => 'https://register.url',
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
    public function testCreateRedirectUrl(string $random, array $rawConfig, string $expectedUrl, bool $addLoginPrompt = false): void
    {
        $configService = $this->createLoginConfigService($rawConfig);
        $loginConfig = $configService->getConfig();
        static::assertInstanceOf(LoginConfig::class, $loginConfig);

        $result = $configService->createRedirectUrl($random, $addLoginPrompt);
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
     * @return iterable<string, array{random: string, rawConfig: array<string, string|bool>, expectedUrl: string}>
     */
    public static function createRedirectUrlTestDataProvider(): iterable
    {
        yield 'default test case' => [
            'random' => 'justARandomString',
            'rawConfig' => [
                'use_default' => true,
                'client_id' => 'justAClientID',
                'client_secret' => 'justAClientSecret',
                'redirect_uri' => 'https://justARedirectUri.org',
                'base_url' => 'https://justABaseUrl.net',
                'authorize_path' => '/authorize',
                'token_path' => '/token',
                'jwks_path' => '/jwks.json',
                'scope' => 'scope',
                'register_url' => 'https://register.url',
            ],
            'expectedUrl' => 'https://justABaseUrl.net/authorize?client_id=justAClientID&redirect_uri=https%3A%2F%2FjustARedirectUri.org&response_type=code&scope=scope&state=api.oauth.sso.code%3Frdm%3DjustARandomString',
        ];
        yield 'with login prompt' => [
            'random' => 'justARandomString',
            'rawConfig' => [
                'use_default' => true,
                'client_id' => 'anotherClientID',
                'client_secret' => 'anotherClientSecret',
                'redirect_uri' => 'https://another-redirect-url.org',
                'base_url' => 'https://another-base-url.net',
                'authorize_path' => '/authorize',
                'token_path' => '/token',
                'jwks_path' => '/jwks.json',
                'scope' => 'scope',
                'register_url' => 'https://register.url',
            ],
            'expectedUrl' => 'https://another-base-url.net/authorize?client_id=anotherClientID&redirect_uri=https%3A%2F%2Fanother-redirect-url.org&response_type=code&scope=scope&state=api.oauth.sso.code%3Frdm%3DjustARandomString&prompt=login',
            'addLoginPrompt' => true,
        ];
    }

    /**
     * @param array<string, string|bool> $rawConfig
     */
    public function createLoginConfigService(array $rawConfig): LoginConfigService
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(static function ($name, $parameter) {
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
            'redirect_uri' => 'https://redirect.url',
            'base_url' => 'https://base.url',
            'authorize_path' => '/authorize',
            'token_path' => '/token',
            'jwks_path' => '/jwks.json',
            'scope' => 'scope',
            'register_url' => 'https://register.url',
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
