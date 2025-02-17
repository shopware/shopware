<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Login\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Login\Config\LoginConfig;
use Shopware\Administration\Login\Config\LoginConfigService;
use Shopware\Administration\Login\Exception\LoginException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(LoginConfigService::class)]
class LoginConfigServiceTest extends TestCase
{
    public function testGetConfigWithEmptyRawConfig(): void
    {
        $configService = new LoginConfigService([], 'http://app.url', '/admin');

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
            'authorize_endpoint' => '/authorize',
            'token_endpoint' => '/token',
        ];

        $configService = new LoginConfigService($rawConfig, 'http://app.url', '/admin');

        $config = $configService->getConfig();

        static::assertNotNull($config);
        static::assertSame($rawConfig['use_default'], $config->useDefault);
        static::assertSame($rawConfig['client_id'], $config->clientId);
        static::assertSame($rawConfig['client_secret'], $config->clientSecret);
        static::assertSame($rawConfig['redirect_uri'], $config->redirectUri);
        static::assertSame($rawConfig['base_url'], $config->baseUrl);
        static::assertSame($rawConfig['authorize_endpoint'], $config->authorizeEndpoint);
        static::assertSame($rawConfig['token_endpoint'], $config->tokenEndpoint);
    }

    /**
     * @param array{use_default: bool, client_id: non-empty-string, client_secret: non-empty-string, redirect_uri: non-empty-string, base_url: non-empty-string} $rawConfig
     */
    #[DataProvider('getConfigErrorsTestDataProvider')]
    public function testGetConfigErrors(array $rawConfig, string $exceptionMessage): void
    {
        $configService = new LoginConfigService($rawConfig, 'http://app.url', '/admin');

        try {
            $configService->getConfig();
        } catch (LoginException $loginException) {
            static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $loginException->getStatusCode());
            static::assertSame(LoginException::LOGIN_CONFIG_INCOMPLETE_OR_MISCONFIGURED, $loginException->getErrorCode());
            static::assertSame($exceptionMessage, $loginException->getMessage());

            return;
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getConfigErrorsTestDataProvider(): array
    {
        return [
            'use_default is not set' => [
                'config' => [
                    'client_id' => 'clientId',
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => 'http://base.url',
                    'authorize_endpoint' => '/authorize',
                    'token_endpoint' => '/token',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [use_default] is missing',
            ],

            'use_default is null' => [
                'config' => [
                    'use_default' => null,
                    'client_id' => 'clientId',
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => 'http://base.url',
                    'authorize_endpoint' => '/authorize',
                    'token_endpoint' => '/token',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [use_default] is null',
            ],

            'use_default is not a bool' => [
                'config' => [
                    'use_default' => 'asd',
                    'client_id' => 'clientId',
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => 'http://base.url',
                    'authorize_endpoint' => '/authorize',
                    'token_endpoint' => '/token',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [use_default] is not a boolean',
            ],

            'client_id is not set' => [
                'config' => [
                    'use_default' => false,
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => 'http://base.url',
                    'authorize_endpoint' => '/authorize',
                    'token_endpoint' => '/token',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_id] is missing',
            ],

            'client_id is null' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => null,
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => 'http://base.url',
                    'authorize_endpoint' => '/authorize',
                    'token_endpoint' => '/token',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_id] is null, [client_id] is blank',
            ],

            'client_id is blank' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => '',
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => 'http://base.url',
                    'authorize_endpoint' => '/authorize',
                    'token_endpoint' => '/token',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_id] is blank',
            ],

            'client_id is no a string' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 12,
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => 'http://base.url',
                    'authorize_endpoint' => '/authorize',
                    'token_endpoint' => '/token',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_id] is invalid string',
            ],

            'client_secret is not set' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 'clientId',
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => 'http://base.url',
                    'authorize_endpoint' => '/authorize',
                    'token_endpoint' => '/token',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_secret] is missing',
            ],

            'client_secret is null' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 'clientId',
                    'client_secret' => null,
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => 'http://base.url',
                    'authorize_endpoint' => '/authorize',
                    'token_endpoint' => '/token',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_secret] is null, [client_secret] is blank',
            ],

            'client_secret is blank' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 'clientId',
                    'client_secret' => '',
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => 'http://base.url',
                    'authorize_endpoint' => '/authorize',
                    'token_endpoint' => '/token',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_secret] is blank',
            ],

            'client_secret is no a string' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 'clientId',
                    'client_secret' => 12,
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => 'http://base.url',
                    'authorize_endpoint' => '/authorize',
                    'token_endpoint' => '/token',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_secret] is invalid string',
            ],

            'redirect_uri is not set' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 'clientId',
                    'client_secret' => 'clientSecret',
                    'base_url' => 'http://base.url',
                    'authorize_endpoint' => '/authorize',
                    'token_endpoint' => '/token',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [redirect_uri] is missing',
            ],

            'redirect_uri is null' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 'clientId',
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => null,
                    'base_url' => 'http://base.url',
                    'authorize_endpoint' => '/authorize',
                    'token_endpoint' => '/token',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [redirect_uri] is null, [redirect_uri] is blank',
            ],

            'redirect_uri is blank' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 'clientId',
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => '',
                    'base_url' => 'http://base.url',
                    'authorize_endpoint' => '/authorize',
                    'token_endpoint' => '/token',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [redirect_uri] is blank',
            ],

            'redirect_uri is no a string' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 'clientId',
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => 12,
                    'base_url' => 'http://base.url',
                    'authorize_endpoint' => '/authorize',
                    'token_endpoint' => '/token',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [redirect_uri] is invalid string, [redirect_uri] is invalid URL',
            ],

            'redirect_uri is no a url' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 'clientId',
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => 'redirectUri',
                    'base_url' => 'http://base.url',
                    'authorize_endpoint' => '/authorize',
                    'token_endpoint' => '/token',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [redirect_uri] is invalid URL',
            ],

            'base_url is not set' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 'clientId',
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => 'http://redirect.url',
                    'authorize_endpoint' => '/authorize',
                    'token_endpoint' => '/token',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [base_url] is missing',
            ],

            'base_url is null' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 'clientId',
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => null,
                    'authorize_endpoint' => '/authorize',
                    'token_endpoint' => '/token',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [base_url] is null, [base_url] is blank',
            ],

            'base_url is blank' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 'clientId',
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => '',
                    'authorize_endpoint' => '/authorize',
                    'token_endpoint' => '/token',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [base_url] is blank',
            ],

            'base_url is not a string' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 'clientId',
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => 12,
                    'authorize_endpoint' => '/authorize',
                    'token_endpoint' => '/token',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [base_url] is invalid string, [base_url] is invalid URL',
            ],

            'base_url is no a url' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 'clientId',
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => 'baseUrl',
                    'authorize_endpoint' => '/authorize',
                    'token_endpoint' => '/token',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [base_url] is invalid URL',
            ],

            'authorize_endpoint is null' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 'clientId',
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => 'http://base.url',
                    'token_endpoint' => '/token',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [authorize_endpoint] is missing',
            ],

            'authorize_endpoint is blank' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 'clientId',
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => 'http://base.url',
                    'authorize_endpoint' => '',
                    'token_endpoint' => '/token',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [authorize_endpoint] is blank',
            ],

            'authorize_endpoint is not a string' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 'clientId',
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => 'http://base.url',
                    'authorize_endpoint' => 12,
                    'token_endpoint' => '/token',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [authorize_endpoint] is invalid string',
            ],

            'token_endpoint is null' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 'clientId',
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => 'http://base.url',
                    'authorize_endpoint' => '/authorize',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [token_endpoint] is missing',
            ],

            'token_endpoint is blank' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 'clientId',
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => 'http://base.url',
                    'authorize_endpoint' => '/authorize',
                    'token_endpoint' => '',
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [token_endpoint] is blank',
            ],

            'token_endpoint is not a string' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 'clientId',
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => 'http://base.url',
                    'authorize_endpoint' => '/authorize',
                    'token_endpoint' => 24,
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [token_endpoint] is invalid string',
            ],
        ];
    }

    public function testCreateTemplateDataWithNullAsLoginConfig(): void
    {
        $configService = new LoginConfigService([], 'http://app.url', '/admin');

        $result = $configService->createTemplateData('randomString', null);

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
            'authorize_endpoint' => '/authorize',
            'token_endpoint' => '/token',
        ];

        $configService = new LoginConfigService($rawConfig, 'http://app.url', '/admin');
        $loginConfig = $configService->getConfig();

        $result = $configService->createTemplateData('randomString', $loginConfig);

        static::assertFalse($result->useDefault);
        static::assertSame('http://app.url/admin/sso/auth?rdm=randomString', $result->url);
    }

    #[DataProvider('createTemplateDataShouldRemovePrefixedSlashesTestDataProvider')]
    public function testCreateTemplateDataShouldRemovePrefixedSlashes(string $adminPath): void
    {
        $rawConfig = [
            'use_default' => false,
            'client_id' => 'clientId',
            'client_secret' => 'clientSecret',
            'redirect_uri' => 'http://redirect.url',
            'base_url' => 'http://base.url',
            'authorize_endpoint' => '/authorize',
            'token_endpoint' => '/token',
        ];

        $configService = new LoginConfigService($rawConfig, 'http://app.url', $adminPath);
        $loginConfig = $configService->getConfig();

        $result = $configService->createTemplateData('randomString', $loginConfig);

        static::assertFalse($result->useDefault);
        static::assertSame('http://app.url/admin/sso/auth?rdm=randomString', $result->url);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function createTemplateDataShouldRemovePrefixedSlashesTestDataProvider(): array
    {
        return [
            'one slash' => [
                'adminPath' => '/admin',
            ],
            'three slashes' => [
                'adminPath' => '///admin',
            ],
            'six slashes' => [
                'adminPath' => '//////admin',
            ],
            'fourteen slashes' => [
                'adminPath' => '//////////////admin',
            ],
        ];
    }

    #[DataProvider('createRedirectUrlTestDataProvider')]
    public function testCreateRedirectUrl(string $random, LoginConfig $loginConfig, string $expectedUrl): void
    {
        $appUrl = 'http://app.url';
        $configService = new LoginConfigService([], $appUrl, '/admin');

        $result = $configService->createRedirectUrl($random, $loginConfig);
        static::assertStringStartsWith($loginConfig->baseUrl, $result);

        // check query parameter
        $query = $this->getQueryParamsAsArray($result);
        static::assertSame($loginConfig->clientId, $query['client_id']);
        static::assertSame($loginConfig->redirectUri, $query['redirect_uri']);

        static::assertIsString($query['state']);

        // check state and query parameter
        static::assertArrayHasKey('state', $query);
        static::assertStringStartsWith('http://app.url', $query['state']);

        $stateUrlQuery = $this->getQueryParamsAsArray($query['state']);
        static::assertSame($random, $stateUrlQuery['rdm']);

        // check given expected url
        static::assertSame($expectedUrl, $result);
    }

    /**
     * @return array<string, array{random: string, loginConfig: LoginConfig, expectedUrl: string}>
     */
    public static function createRedirectUrlTestDataProvider(): array
    {
        return [
            'Test case one' => [
                'random' => 'justARandomString',
                'loginConfig' => new LoginConfig(
                    true,
                    'justAClientID',
                    'justAClientSecret',
                    'http://justARedirectUri.org',
                    'http://justABaseUrl.net',
                    '/authorize',
                    '/token',
                ),
                'expectedUrl' => 'http://justABaseUrl.net/authorize?client_id=justAClientID&redirect_uri=http%3A%2F%2FjustARedirectUri.org&response_type=code&scope=openid&state=http%3A%2F%2Fapp.url%2Fapi%2Foauth%2Fsso%2Fcode%3Frdm%3DjustARandomString',
            ],

            'Test case two' => [
                'random' => 'justARandomString',
                'loginConfig' => new LoginConfig(
                    true,
                    'anotherClientID',
                    'anotherClientSecret',
                    'http://another-redirect-url.org',
                    'http://another-base-url.net',
                    '/authorize',
                    '/token',
                ),
                'expectedUrl' => 'http://another-base-url.net/authorize?client_id=anotherClientID&redirect_uri=http%3A%2F%2Fanother-redirect-url.org&response_type=code&scope=openid&state=http%3A%2F%2Fapp.url%2Fapi%2Foauth%2Fsso%2Fcode%3Frdm%3DjustARandomString',
            ],
        ];
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
