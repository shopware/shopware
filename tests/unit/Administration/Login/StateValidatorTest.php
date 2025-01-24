<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Login;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Login\Config\LoginConfig;
use Shopware\Administration\Login\Exception\LoginException;
use Shopware\Administration\Login\StateValidator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(StateValidator::class)]
class StateValidatorTest extends TestCase
{
    #[DataProvider('validateTestDataProvider')]
    public function testValidate(?string $state, ?string $storedState, bool $expectException): void
    {
        $loginConfig = $this->createLoginConfig();
        $validator = new StateValidator($loginConfig);

        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with($loginConfig->getSessionKey())->willReturn($storedState);

        $code = Uuid::randomHex();

        $request = new Request(['rdm' => $state, 'code' => $code]);
        $request->setSession($session);

        try {
            $validator->validateRequest($request);
        } catch (\Throwable $exception) {
            static::assertInstanceOf(LoginException::class, $exception);

            if ($expectException) {
                static::assertSame('Invalid login state', $exception->getMessage());
                static::assertSame(Response::HTTP_UNAUTHORIZED, $exception->getStatusCode());
                static::assertSame(LoginException::LOGIN_INVALID_LOGIN_STATE, $exception->getErrorCode());

                return;
            }
        }

        static::assertSame('shopware_grant', $request->get('grant_type'));
        static::assertSame($code, $request->get('code'));
    }

    public static function validateTestDataProvider(): array
    {
        $validRandom = self::createRandom(LoginConfig::RANDOM_LENGTH);

        return [
            'state and storedState is null' => [
                'state' => null,
                'storedState' => null,
                'expectException' => true,
            ],

            'state is empty and storedState is null' => [
                'state' => '',
                'storedState' => null,
                'expectException' => true,
            ],

            'state is empty and storedState is empty' => [
                'state' => '',
                'storedState' => '',
                'expectException' => true,
            ],

            'state has invalid length and storedState is set' => [
                'state' => self::createRandom(99),
                'storedState' => self::createRandom(LoginConfig::RANDOM_LENGTH),
                'expectException' => true,
            ],

            'state has valid length and storedState is different' => [
                'state' => self::createRandom(LoginConfig::RANDOM_LENGTH),
                'storedState' => self::createRandom(LoginConfig::RANDOM_LENGTH),
                'expectException' => true,
            ],

            'state is valid and storedState is null' => [
                'state' => self::createRandom(LoginConfig::RANDOM_LENGTH),
                'storedState' => null,
                'expectException' => true,
            ],

            'state is valid and storedState equals' => [
                'state' => $validRandom,
                'storedState' => $validRandom,
                'expectException' => false,
            ],
        ];
    }

    private function createLoginConfig(): LoginConfig
    {
        return new LoginConfig(
            [
                'use_default' => true,
                'client_id' => 'client-id',
                'client_secret' => 'client-secret',
                'redirect_uri' => 'https://redirect.uri',
                'base_url' => 'https://base.url',
                'session_key' => 'session-key',
            ],
            'http://app.url',
            '/admin',
        );
    }

    private static function createRandom(int $length): string
    {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charsLength = \strlen($chars);
        $randomString = '';

        for ($i = 0; $i < $length; ++$i) {
            $randomString .= $chars[\random_int(0, $charsLength - 1)];
        }

        return $randomString;
    }
}
