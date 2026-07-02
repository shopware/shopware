<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\UserRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserEntity;

/**
 * @internal
 */
#[CoversClass(UserRepository::class)]
class UserRepositoryTest extends TestCase
{
    public function testLoginWithCorrectCredentials(): void
    {
        $username = 'my_username';
        $password = 'secure-test';

        $user = new UserEntity();
        $user->setId(Uuid::randomBytes());
        $user->setUsername($username);
        $user->setPassword(password_hash($password, \PASSWORD_BCRYPT));
        $user->setActive(true);

        $userRepository = $this->createUserRepository($user);

        $clientEntity = $this->createMock(ClientEntityInterface::class);
        $response = $userRepository->getUserEntityByUserCredentials(
            $username,
            $password,
            'password',
            $clientEntity
        );

        static::assertNotNull($response);
    }

    public function testLoginWithWrongPassword(): void
    {
        $username = 'my_username';
        $password = 'secure-test';

        $user = new UserEntity();
        $user->setId(Uuid::randomBytes());
        $user->setUsername($username);
        $user->setPassword(password_hash($password, \PASSWORD_BCRYPT));
        $user->setActive(true);

        $userRepository = $this->createUserRepository($user);

        $clientEntity = $this->createMock(ClientEntityInterface::class);
        $response = $userRepository->getUserEntityByUserCredentials(
            $username,
            'secure-test-wrong',
            'password',
            $clientEntity
        );

        static::assertNull($response);
    }

    public function testLoginWithNoUserFound(): void
    {
        $username = 'my_username';
        $password = 'secure-test';

        $userRepository = $this->createUserRepository(null);

        $clientEntity = $this->createMock(ClientEntityInterface::class);
        $response = $userRepository->getUserEntityByUserCredentials(
            $username,
            $password,
            'password',
            $clientEntity
        );

        static::assertNull($response);
    }

    public function testLoginWithInactiveUser(): void
    {
        $username = 'my_username';
        $password = 'secure-test';

        $user = new UserEntity();
        $user->setId(Uuid::randomBytes());
        $user->setUsername($username);
        $user->setPassword(password_hash($password, \PASSWORD_BCRYPT));
        $user->setActive(false);

        $userRepository = $this->createUserRepository($user);

        $clientEntity = $this->createMock(ClientEntityInterface::class);
        $response = $userRepository->getUserEntityByUserCredentials(
            $username,
            $password,
            'password',
            $clientEntity
        );

        static::assertNull($response);
    }

    protected function createUserRepository(?UserEntity $user): UserRepository
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
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

        $connection = $this->createMock(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        return new UserRepository($connection);
    }
}
