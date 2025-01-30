<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Login;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Login\Config\LoginConfig;
use Shopware\Administration\Login\Exception\LoginException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(LoginConfig::class)]
class LoginConfigTest extends TestCase
{
    /**
     * @param array<string, array<string, mixed>> $config
     */
    #[DataProvider('createTestDataProvider')]
    public function testCreateWithInvalidConfig(array $config, string $expectedMessage): void
    {
        try {
            new LoginConfig($config, 'http://test.com', '/admin');
        } catch (LoginException $exception) {
            static::assertSame($expectedMessage, $exception->getMessage());
            static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
            static::assertSame(LoginException::LOGIN_CONFIG_INCOMPLETE_OR_MISCONFIGURED, $exception->getErrorCode());
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function createTestDataProvider(): array
    {
        return [
            'contains use_default' => [
                'config' => [
                    'use_default' => false,
                ],
                'expectedMessage' => 'Login config is incomplete or misconfigured. Field errors: [client_id] is missing, [client_secret] is missing, [redirect_uri] is missing, [base_url] is missing, [session_key] is missing',
            ],

            'contains empty client_id' => [
                'config' => [
                    'client_id' => '',
                ],
                'expectedMessage' => 'Login config is incomplete or misconfigured. Field errors: [use_default] is missing, [client_id] is blank, [client_secret] is missing, [redirect_uri] is missing, [base_url] is missing, [session_key] is missing',
            ],

            'contains client_id' => [
                'config' => [
                    'client_id' => 'clientID',
                ],
                'expectedMessage' => 'Login config is incomplete or misconfigured. Field errors: [use_default] is missing, [client_secret] is missing, [redirect_uri] is missing, [base_url] is missing, [session_key] is missing',
            ],

            'contains empty client_secret' => [
                'config' => [
                    'client_secret' => '',
                ],
                'expectedMessage' => 'Login config is incomplete or misconfigured. Field errors: [use_default] is missing, [client_id] is missing, [client_secret] is blank, [redirect_uri] is missing, [base_url] is missing, [session_key] is missing',
            ],

            'contains client_secret' => [
                'config' => [
                    'client_secret' => '',
                ],
                'expectedMessage' => 'Login config is incomplete or misconfigured. Field errors: [use_default] is missing, [client_id] is missing, [client_secret] is blank, [redirect_uri] is missing, [base_url] is missing, [session_key] is missing',
            ],

            'contains empty redirect_uri' => [
                'config' => [
                    'redirect_uri' => '',
                ],
                'expectedMessage' => 'Login config is incomplete or misconfigured. Field errors: [use_default] is missing, [client_id] is missing, [client_secret] is missing, [redirect_uri] is blank, [base_url] is missing, [session_key] is missing',
            ],

            'contains invalid redirect_uri' => [
                'config' => [
                    'redirect_uri' => 'invalid_redirect_uri',
                ],
                'expectedMessage' => 'Login config is incomplete or misconfigured. Field errors: [use_default] is missing, [client_id] is missing, [client_secret] is missing, [redirect_uri] is invalid URL, [base_url] is missing, [session_key] is missing',
            ],

            'contains redirect_uri' => [
                'config' => [
                    'redirect_uri' => 'http://redirect_uri.com',
                ],
                'expectedMessage' => 'Login config is incomplete or misconfigured. Field errors: [use_default] is missing, [client_id] is missing, [client_secret] is missing, [base_url] is missing, [session_key] is missing',
            ],

            'contains empty base_url' => [
                'config' => [
                    'base_url' => '',
                ],
                'expectedMessage' => 'Login config is incomplete or misconfigured. Field errors: [use_default] is missing, [client_id] is missing, [client_secret] is missing, [redirect_uri] is missing, [base_url] is blank, [session_key] is missing',
            ],

            'contains invalid base_url' => [
                'config' => [
                    'base_url' => 'invalid_base_url',
                ],
                'expectedMessage' => 'Login config is incomplete or misconfigured. Field errors: [use_default] is missing, [client_id] is missing, [client_secret] is missing, [redirect_uri] is missing, [base_url] is invalid URL, [session_key] is missing',
            ],

            'contains base_url' => [
                'config' => [
                    'base_url' => 'http://base_url.com',
                ],
                'expectedMessage' => 'Login config is incomplete or misconfigured. Field errors: [use_default] is missing, [client_id] is missing, [client_secret] is missing, [redirect_uri] is missing, [session_key] is missing',
            ],
        ];
    }

    public function testCreateWithEmptyConfig(): void
    {
        $config = new LoginConfig([], 'http://test.com', '/admin');

        static::assertTrue($config->isEmpty());
        static::assertTrue($config->getUseDefault());
        static::assertNull($config->getClientId());
        static::assertNull($config->getClientSecret());
        static::assertNull($config->getRedirectUri());
        static::assertNull($config->getBaseUrl());
    }

    public function testCreateWithValidConfig(): void
    {
        $config = new LoginConfig(
            [
                'use_default' => false,
                'client_id' => 'client_id',
                'client_secret' => 'client_secret',
                'redirect_uri' => 'https://redirect_uri.com',
                'base_url' => 'https://base_url.com',
            ],
            'http://test.com',
            '/admin'
        );

        static::assertFalse($config->isEmpty());
        static::assertFalse($config->getUseDefault());
        static::assertSame('client_id', $config->getClientId());
        static::assertSame('client_secret', $config->getClientSecret());
        static::assertSame('https://redirect_uri.com', $config->getRedirectUri());
        static::assertSame('https://base_url.com', $config->getBaseUrl());
    }

    public function testCreateTemplateData(): void
    {
        $config = new LoginConfig(
            [
                'use_default' => true,
                'client_id' => 'client_id',
                'client_secret' => 'client_secret',
                'redirect_uri' => 'https://redirect_uri.com',
                'base_url' => 'https://base_url.com',
            ],
            'http://test.com',
            '/admin'
        );

        $templateData = $config->createTemplateData();
        static::assertSame(LoginConfig::RANDOM_LENGTH, \strlen($templateData->random));
        static::assertTrue($templateData->show);
        static::assertTrue($templateData->useDefault);
        static::assertStringEndsWith($templateData->random, $templateData->url);
    }

    public function testCreateRedirectUrl(): void
    {
        $config = new LoginConfig(
            [
                'use_default' => true,
                'client_id' => 'client_id',
                'client_secret' => 'client_secret',
                'redirect_uri' => 'https://redirect_uri.com',
                'base_url' => 'https://base_url.com',
            ],
            'http://test.com',
            '/admin'
        );

        $url = $config->createRedirectUrl('123456');
        static::assertSame(
            'https://base_url.com/oauth/authorize?client_id=client_id&redirect_uri=https%3A%2F%2Fredirect_uri.com&response_type=code&scope=openid&state=http%3A%2F%2Ftest.com%2Fapi%2Foauth%2Fsso%2Fcode%3Frdm%3D123456',
            $url
        );
    }
}
