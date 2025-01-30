<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Login\UserService;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Login\Exception\LoginException;
use Shopware\Administration\Login\UserService\ExternalAuthUser;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ExternalAuthUser::class)]
class ExternalAuthUserTest extends TestCase
{
    public function testCreate(): void
    {
        $expiry = new \DateTimeImmutable();
        $externalAuthUser = ExternalAuthUser::create(
            [
                'id' => 'id_value',
                'user_id' => 'user_id_value',
                'user_sub' => 'user_sub_value',
                'refresh_token' => 'refresh_token_value',
                'expiry' => $expiry,
                'email' => 'test@example.com',
                'is_new' => false,
            ]
        );

        static::assertSame('id_value', $externalAuthUser->id);
        static::assertSame('user_id_value', $externalAuthUser->userId);
        static::assertSame('user_sub_value', $externalAuthUser->sub);
        static::assertSame('refresh_token_value', $externalAuthUser->refreshToken);
        static::assertSame($expiry, $externalAuthUser->expiry);
        static::assertSame('test@example.com', $externalAuthUser->email);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('createTestDataProvider')]
    public function testCreateWithValidationErrors(array $data, string $expected): void
    {
        try {
            static::assertArrayHasKey('id', $data);
            static::assertArrayHasKey('user_id', $data);
            static::assertArrayHasKey('user_sub', $data);
            static::assertArrayHasKey('refresh_token', $data);
            static::assertArrayHasKey('expiry', $data);
            static::assertArrayHasKey('email', $data);
            static::assertArrayHasKey('is_new', $data);
            ExternalAuthUser::create($data);
        } catch (LoginException $exception) {
            static::assertSame($expected, $exception->getMessage());
            static::assertSame(Response::HTTP_UNAUTHORIZED, $exception->getStatusCode());
            static::assertSame(LoginException::LOGIN_USER_INVALID, $exception->getErrorCode());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function createTestDataProvider(): array
    {
        return [
            'all is null' => [
                'data' => [
                    'id' => null,
                    'user_id' => null,
                    'user_sub' => null,
                    'refresh_token' => null,
                    'expiry' => null,
                    'email' => null,
                    'is_new' => false,
                ],
                'expected' => 'Login user invalid: [user_id]: is required, [user_sub]: is required, [email]: is required',
            ],

            'all is blank' => [
                'data' => [
                    'id' => '',
                    'user_id' => '',
                    'user_sub' => '',
                    'refresh_token' => '',
                    'expiry' => '',
                    'email' => '',
                    'is_new' => false,
                ],
                'expected' => 'Login user invalid: [user_id]: is required, [user_sub]: is required, [expiry]: Needs to be a DateTimeInterface, [email]: is required',
            ],

            'id is invalid' => [
                'data' => [
                    'id' => 12,
                    'user_id' => 'user_id',
                    'user_sub' => 'user_sub',
                    'refresh_token' => 'refresh_token',
                    'expiry' => new \DateTimeImmutable(),
                    'email' => 'test@example.com',
                    'is_new' => false,
                ],
                'expected' => 'Login user invalid: [id]: This value should be of type string.',
            ],

            'user_id is invalid' => [
                'data' => [
                    'id' => 'id',
                    'user_id' => 12,
                    'user_sub' => 'user_sub',
                    'refresh_token' => 'refresh_token',
                    'expiry' => new \DateTimeImmutable(),
                    'email' => 'test@example.com',
                    'is_new' => false,
                ],
                'expected' => 'Login user invalid: [user_id]: Needs to be a string',
            ],

            'user_sub is invalid' => [
                'data' => [
                    'id' => 'id',
                    'user_id' => 'user_id',
                    'user_sub' => 12,
                    'refresh_token' => 'refresh_token',
                    'expiry' => new \DateTimeImmutable(),
                    'email' => 'test@example.com',
                    'is_new' => false,
                ],
                'expected' => 'Login user invalid: [user_sub]: Needs to be a string',
            ],

            'refresh_token is invalid' => [
                'data' => [
                    'id' => 'id',
                    'user_id' => 'user_id',
                    'user_sub' => 'user_sub',
                    'refresh_token' => 12,
                    'expiry' => new \DateTimeImmutable(),
                    'email' => 'test@example.com',
                    'is_new' => false,
                ],
                'expected' => 'Login user invalid: [refresh_token]: Needs to be a string',
            ],

            'expiry is invalid' => [
                'data' => [
                    'id' => 'id',
                    'user_id' => 'user_id',
                    'user_sub' => 'user_sub',
                    'refresh_token' => 'refresh_token',
                    'expiry' => '12-12-1212',
                    'email' => 'test@example.com',
                    'is_new' => false,
                ],
                'expected' => 'Login user invalid: [expiry]: Needs to be a DateTimeInterface',
            ],

            'email is invalid' => [
                'data' => [
                    'id' => 'id',
                    'user_id' => 'user_id',
                    'user_sub' => 'user_sub',
                    'refresh_token' => 'refresh_token',
                    'expiry' => new \DateTimeImmutable(),
                    'email' => 'test.com',
                    'is_new' => false,
                ],
                'expected' => 'Login user invalid: [email]: Needs to be a valid email address',
            ],
        ];
    }
}
