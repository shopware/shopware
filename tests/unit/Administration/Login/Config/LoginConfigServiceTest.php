<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Login\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
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
        ];
        $configService = new LoginConfigService($rawConfig, 'http://app.url', '/admin');

        $config = $configService->getConfig();

        static::assertSame($rawConfig['use_default'], $config->useDefault);
        static::assertSame($rawConfig['client_id'], $config->clientId);
        static::assertSame($rawConfig['client_secret'], $config->clientSecret);
        static::assertSame($rawConfig['redirect_uri'], $config->redirectUri);
        static::assertSame($rawConfig['base_url'], $config->baseUrl);
    }

    #[DataProvider('getConfigErrorsTestDataProvider')]
    public function testGetConfigErrors(array $config, string $exceptionMessage): void
    {
        $configService = new LoginConfigService($config, 'http://app.url', '/admin');

        try {
            $configService->getConfig();
        } catch (LoginException $loginException) {
            static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $loginException->getStatusCode());
            static::assertSame(LoginException::LOGIN_CONFIG_INCOMPLETE_OR_MISCONFIGURED, $loginException->getErrorCode());
            static::assertSame($exceptionMessage, $loginException->getMessage());

            return;
        }
    }

    public static function getConfigErrorsTestDataProvider(): array
    {
        return [
            'use_default is not set' => [
                'config' => [
                    'client_id' => 'clientId',
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => 'http://base.url',
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
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [use_default] is not a boolean',
            ],

            'client_id is not set' => [
                'config' => [
                    'use_default' => false,
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => 'http://base.url',
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
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_id] is invalid string',
            ],

            'client_secret is not set' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 'clientId',
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => 'http://base.url',
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
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_secret] is invalid string',
            ],

            'redirect_uri is not set' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 'clientId',
                    'client_secret' => 'clientSecret',
                    'base_url' => 'http://base.url',
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
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [redirect_uri] is invalid URL',
            ],

            'base_url is not set' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 'clientId',
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => 'http://redirect.url',
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
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [base_url] is blank',
            ],

            'base_url is no a string' => [
                'config' => [
                    'use_default' => false,
                    'client_id' => 'clientId',
                    'client_secret' => 'clientSecret',
                    'redirect_uri' => 'http://redirect.url',
                    'base_url' => 12,
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
                ],
                'exceptionMessage' => 'Login config is incomplete or misconfigured. Field errors: [base_url] is invalid URL',
            ],
        ];
    }
}
