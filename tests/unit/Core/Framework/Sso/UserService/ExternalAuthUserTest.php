<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Sso\UserService;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\SsoException;
use Shopware\Core\Framework\Sso\UserService\ExternalAuthUser;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
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
                'token' => ['token' => Uuid::randomHex(), 'refreshToken' => Uuid::randomHex()],
                'expiry' => $expiry,
                'email' => 'test@example.com',
            ]
        );

        static::assertSame('id_value', $externalAuthUser->id);
        static::assertSame('user_id_value', $externalAuthUser->userId);
        static::assertSame('user_sub_value', $externalAuthUser->sub);
        static::assertSame($expiry, $externalAuthUser->expiry);
        static::assertSame('test@example.com', $externalAuthUser->email);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('createTestDataProvider')]
    public function testCreateWithValidationErrors(array $data, string $expected): void
    {
        static::assertArrayHasKey('id', $data);
        static::assertArrayHasKey('user_id', $data);
        static::assertArrayHasKey('user_sub', $data);
        static::assertArrayHasKey('token', $data);
        static::assertArrayHasKey('expiry', $data);
        static::assertArrayHasKey('email', $data);

        $missingFields = \explode(', ', \substr($expected, \strlen('Login user invalid: ')));
        $this->expectExceptionObject(SsoException::loginUserInvalid($missingFields));

        try {
            ExternalAuthUser::create($data);
        } catch (SsoException $exception) {
            static::assertSame(Response::HTTP_UNAUTHORIZED, $exception->getStatusCode());
            static::assertSame(SsoException::SSO_LOGIN_USER_INVALID, $exception->getErrorCode());

            throw $exception;
        }
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function createTestDataProvider(): iterable
    {
        yield 'all is null' => [
            'data' => [
                'id' => null,
                'user_id' => null,
                'user_sub' => null,
                'token' => null,
                'expiry' => null,
                'email' => null,
            ],
            'expected' => 'Login user invalid: [id]: is required, [user_id]: is required, [user_sub]: is required, [email]: is required',
        ];
        yield 'all is blank' => [
            'data' => [
                'id' => '',
                'user_id' => '',
                'user_sub' => '',
                'token' => '',
                'expiry' => '',
                'email' => '',
            ],
            'expected' => 'Login user invalid: [id]: is required, [user_id]: is required, [user_sub]: is required, [token]: Needs to be an array, [token]: This value should be of type array|(Traversable&ArrayAccess)., [expiry]: Needs to be a DateTimeInterface, [email]: is required',
        ];
        yield 'id is invalid' => [
            'data' => [
                'id' => 12,
                'user_id' => 'user_id',
                'user_sub' => 'user_sub',
                'token' => ['token' => Uuid::randomHex(), 'refreshToken' => Uuid::randomHex()],
                'expiry' => new \DateTimeImmutable(),
                'email' => 'test@example.com',
            ],
            'expected' => 'Login user invalid: [id]: This value should be of type string.',
        ];
        yield 'user_id is invalid' => [
            'data' => [
                'id' => 'id',
                'user_id' => 12,
                'user_sub' => 'user_sub',
                'token' => ['token' => Uuid::randomHex(), 'refreshToken' => Uuid::randomHex()],
                'expiry' => new \DateTimeImmutable(),
                'email' => 'test@example.com',
            ],
            'expected' => 'Login user invalid: [user_id]: Needs to be a string',
        ];
        yield 'user_sub is invalid' => [
            'data' => [
                'id' => 'id',
                'user_id' => 'user_id',
                'user_sub' => 12,
                'token' => ['token' => Uuid::randomHex(), 'refreshToken' => Uuid::randomHex()],
                'expiry' => new \DateTimeImmutable(),
                'email' => 'test@example.com',
            ],
            'expected' => 'Login user invalid: [user_sub]: Needs to be a string',
        ];
        yield 'token is invalid' => [
            'data' => [
                'id' => 'id',
                'user_id' => 'user_id',
                'user_sub' => 'user_sub',
                'token' => 12,
                'expiry' => new \DateTimeImmutable(),
                'email' => 'test@example.com',
            ],
            'expected' => 'Login user invalid: [token]: Needs to be an array, [token]: This value should be of type array|(Traversable&ArrayAccess).',
        ];
        yield 'expiry is invalid' => [
            'data' => [
                'id' => 'id',
                'user_id' => 'user_id',
                'user_sub' => 'user_sub',
                'token' => ['token' => Uuid::randomHex(), 'refreshToken' => Uuid::randomHex()],
                'expiry' => '12-12-1212',
                'email' => 'test@example.com',
            ],
            'expected' => 'Login user invalid: [expiry]: Needs to be a DateTimeInterface',
        ];
        yield 'email is invalid' => [
            'data' => [
                'id' => 'id',
                'user_id' => 'user_id',
                'user_sub' => 'user_sub',
                'token' => ['token' => Uuid::randomHex(), 'refreshToken' => Uuid::randomHex()],
                'expiry' => new \DateTimeImmutable(),
                'email' => 'test.com',
            ],
            'expected' => 'Login user invalid: [email]: Needs to be a valid email address',
        ];
    }

    public function testCreateFromDatabaseQuery(): void
    {
        $id = '01980cbe117f713088b2401b26b57275';
        $userId = '01980cbe117f713088b2401b270fd2d5';
        $userSub = 'user_sub';

        $token = '1234567890abcdefghijklmnopqrstuvwxyz';
        $refreshToken = '0987654321zyxwvutsrqponmlkjihgfedcba';

        $expiry = '2025-07-15 06:20:39.679';

        $data = [
            'id' => Uuid::fromHexToBytes($id),
            'user_id' => Uuid::fromHexToBytes($userId),
            'user_sub' => $userSub,
            'token' => \json_encode(['token' => $token, 'refreshToken' => $refreshToken], \JSON_THROW_ON_ERROR),
            'expiry' => $expiry,
            'email' => 'test@test.com',
        ];

        $externalAuthUser = ExternalAuthUser::createFromDatabaseQuery($data);

        static::assertSame($id, $externalAuthUser->id);
        static::assertSame($userId, $externalAuthUser->userId);
        static::assertSame($userSub, $externalAuthUser->sub);
        static::assertSame($token, $externalAuthUser->token?->token);
        static::assertSame($refreshToken, $externalAuthUser->token->refreshToken);
        static::assertSame($expiry, $externalAuthUser->expiry->format(Defaults::STORAGE_DATE_TIME_FORMAT));
    }
}
