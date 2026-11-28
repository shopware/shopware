<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Api\OAuth\UserRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\PasswordHasher\AbstractPasswordHasher;
use Shopware\Core\Framework\PasswordHasher\NativePasswordHasher;
use Shopware\Core\Framework\Sso\Config\LoginConfigService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UserRepository::class)]
class UserRepositoryTest extends TestCase
{
    /**
     * @var list<array{id: string, password: string}>
     */
    private array $passwordUpdates = [];

    public function testLoginWithDefaultLoginEnabledAndCorrectCredentials(): void
    {
        $username = 'my_username';
        $password = 'secure-test';

        $user = new UserEntity();
        $user->setId(Uuid::randomBytes());
        $user->setUsername($username);
        $user->setPassword(password_hash($password, \PASSWORD_BCRYPT));
        $user->setActive(true);

        $userRepository = $this->createUserRepository($user);

        $clientEntity = static::createStub(ClientEntityInterface::class);
        $response = $userRepository->getUserEntityByUserCredentials(
            $username,
            $password,
            'password',
            $clientEntity
        );

        static::assertNotNull($response);
    }

    public function testLoginWithDefaultLoginEnabledAndWrongPassword(): void
    {
        $username = 'my_username';
        $password = 'secure-test';

        $user = new UserEntity();
        $user->setId(Uuid::randomBytes());
        $user->setUsername($username);
        $user->setPassword(password_hash($password, \PASSWORD_BCRYPT));
        $user->setActive(true);

        $userRepository = $this->createUserRepository($user);

        $clientEntity = static::createStub(ClientEntityInterface::class);
        $response = $userRepository->getUserEntityByUserCredentials(
            $username,
            'secure-test-wrong',
            'password',
            $clientEntity
        );

        static::assertNull($response);
    }

    public function testLoginWithDefaultLoginEnabledAndNoUserFound(): void
    {
        $username = 'my_username';
        $password = 'secure-test';

        $passwordHasher = $this->createMock(AbstractPasswordHasher::class);
        $passwordHasher->expects($this->once())->method('verifyPlaceholder')->with($password);

        $userRepository = $this->createUserRepository(null, passwordHasher: $passwordHasher);

        $clientEntity = static::createStub(ClientEntityInterface::class);
        $response = $userRepository->getUserEntityByUserCredentials(
            $username,
            $password,
            'password',
            $clientEntity
        );

        static::assertNull($response);
    }

    public function testLoginWithDefaultLoginDisabled(): void
    {
        $username = 'my_username';
        $password = 'secure-test';

        $user = new UserEntity();
        $user->setId(Uuid::randomBytes());
        $user->setUsername($username);
        $user->setPassword(password_hash($password, \PASSWORD_BCRYPT));
        $user->setActive(true);

        $userRepository = $this->createUserRepository($user, false);

        $clientEntity = static::createStub(ClientEntityInterface::class);
        $response = $userRepository->getUserEntityByUserCredentials(
            $username,
            $password,
            'password',
            $clientEntity
        );

        static::assertNull($response);
    }

    public function testLoginWithDefaultLoginEnabledAndInactiveUser(): void
    {
        $username = 'my_username';
        $password = 'secure-test';

        $user = new UserEntity();
        $user->setId(Uuid::randomBytes());
        $user->setUsername($username);
        $user->setPassword(password_hash($password, \PASSWORD_BCRYPT));
        $user->setActive(false);

        $userRepository = $this->createUserRepository($user);

        $clientEntity = static::createStub(ClientEntityInterface::class);
        $response = $userRepository->getUserEntityByUserCredentials(
            $username,
            $password,
            'password',
            $clientEntity
        );

        static::assertNull($response);
    }

    public function testLoginRehashesOutdatedPasswordHash(): void
    {
        $user = $this->createUser(password_hash('secure-test', \PASSWORD_BCRYPT, ['cost' => 10]));

        $userRepository = $this->createUserRepository($user, passwordHasher: new NativePasswordHasher(options: ['cost' => 11]));

        static::assertNotNull($userRepository->getUserEntityByUserCredentials('my_username', 'secure-test', 'password', static::createStub(ClientEntityInterface::class)));

        static::assertCount(1, $this->passwordUpdates);
        static::assertSame($user->getId(), $this->passwordUpdates[0]['id']);
        static::assertStringStartsWith('$2y$11$', $this->passwordUpdates[0]['password']);
        static::assertTrue(password_verify('secure-test', $this->passwordUpdates[0]['password']));
    }

    public function testLoginKeepsUpToDatePasswordHash(): void
    {
        $user = $this->createUser(password_hash('secure-test', \PASSWORD_BCRYPT, ['cost' => 10]));

        $userRepository = $this->createUserRepository($user);

        static::assertNotNull($userRepository->getUserEntityByUserCredentials('my_username', 'secure-test', 'password', static::createStub(ClientEntityInterface::class)));
        static::assertSame([], $this->passwordUpdates);
    }

    public function testLoginDoesNotRehashForWrongPassword(): void
    {
        $user = $this->createUser(password_hash('secure-test', \PASSWORD_BCRYPT, ['cost' => 10]));

        $userRepository = $this->createUserRepository($user, passwordHasher: new NativePasswordHasher(options: ['cost' => 11]));

        static::assertNull($userRepository->getUserEntityByUserCredentials('my_username', 'wrong', 'password', static::createStub(ClientEntityInterface::class)));
        static::assertSame([], $this->passwordUpdates);
    }

    public function testLoginSucceedsWhenRehashingFails(): void
    {
        $user = $this->createUser(password_hash('secure-test', \PASSWORD_BCRYPT, ['cost' => 10]));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $userRepository = $this->createUserRepository(
            $user,
            passwordHasher: new NativePasswordHasher(options: ['cost' => 11]),
            logger: $logger,
            failingUpdate: true,
        );

        static::assertNotNull($userRepository->getUserEntityByUserCredentials('my_username', 'secure-test', 'password', static::createStub(ClientEntityInterface::class)));
    }

    protected function createUserRepository(
        ?UserEntity $user,
        bool $useDefault = true,
        AbstractPasswordHasher $passwordHasher = new NativePasswordHasher(options: ['cost' => 10]),
        LoggerInterface $logger = new NullLogger(),
        bool $failingUpdate = false,
    ): UserRepository {
        $queryBuilder = static::createStub(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('fetchAssociative')->willReturnCallback(static function () use ($user) {
            if ($user !== null) {
                return $user->jsonSerialize();
            }

            return false;
        });

        $connection = static::createStub(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->method('update')->willReturnCallback(
            /**
             * @param array<string, mixed> $data
             * @param array<string, mixed> $criteria
             */
            function (string $table, array $data, array $criteria) use ($failingUpdate): int {
                if ($failingUpdate) {
                    throw new \RuntimeException('database gone');
                }

                static::assertSame('user', $table);
                static::assertIsString($criteria['id']);
                static::assertIsString($data['password']);
                $this->passwordUpdates[] = ['id' => $criteria['id'], 'password' => $data['password']];

                return 1;
            }
        );

        $loginConfigService = new LoginConfigService(
            [
                'use_default' => $useDefault,
                'client_id' => 'client_id',
                'client_secret' => 'client_secret',
                'redirect_uri' => 'http://redirect.uri',
                'base_url' => 'http://base.uri',
                'authorize_path' => '/authorize',
                'token_path' => '/token',
                'jwks_path' => '/jwks.json',
                'scope' => 'scope',
                'register_url' => 'https://register.url',
            ],
            static::createStub(RouterInterface::class)
        );

        return new UserRepository($connection, $loginConfigService, $passwordHasher, $logger);
    }

    private function createUser(string $passwordHash): UserEntity
    {
        $user = new UserEntity();
        $user->setId(Uuid::randomBytes());
        $user->setUsername('my_username');
        $user->setPassword($passwordHash);
        $user->setActive(true);

        return $user;
    }
}
