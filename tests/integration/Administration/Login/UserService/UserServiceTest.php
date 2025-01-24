<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Login\UserService;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Login\Exception\LoginException;
use Shopware\Administration\Login\UserService\UserService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\RateLimiter\RateLimiterFactory;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Tests\Integration\Administration\Login\Helper\FakeTokenGenerator;
use Shopware\Tests\Integration\Administration\Login\Helper\FakeUserInstaller;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\NullStore;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(UserService::class)]
class UserServiceTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testGetUserWithoutTokenUser(): void
    {
        $userId = Uuid::randomHex();
        $email = 'test@email.com';
        $subject = Uuid::randomHex();

        $fakeUserInstaller = new FakeUserInstaller($this->getContainer()->get(Connection::class));
        $fakeUserInstaller->installBaseUserData($userId, $email);

        $idToken = (new FakeTokenGenerator())->setEmail($email)->setSubject($subject)->generate();
        $refreshToken = Uuid::randomHex();

        $externalAuthUser = $this->createUserService()->getUser($idToken, $refreshToken);
        static::assertSame($userId, $externalAuthUser->userId);
        static::assertSame($refreshToken, $externalAuthUser->refreshToken);
        static::assertTrue($externalAuthUser->isNew);
        static::assertSame($email, $externalAuthUser->email);

        // ensure data is created and updated
        $tokenUserData = $this->getTokenUserData($subject);
        static::assertSame($refreshToken, $tokenUserData['refresh_token']);
        static::assertSame($subject, $tokenUserData['user_sub']);
        static::assertSame($userId, Uuid::fromBytesToHex($tokenUserData['user_id']));
    }

    public function testGetUserWithTokenUser(): void
    {
        $userId = Uuid::randomHex();
        $email = 'anotherFake@email.com';
        $subject = Uuid::randomHex();

        $fakeUserInstaller = new FakeUserInstaller($this->getContainer()->get(Connection::class));
        $fakeUserInstaller->installBaseUserData($userId, $email);
        $fakeUserInstaller->installTokenUser($userId, $subject);

        $idToken = (new FakeTokenGenerator())->setEmail($email)->setSubject($subject)->generate();
        $refreshToken = Uuid::randomHex();

        $externalAuthUser = $this->createUserService()->getUser($idToken, $refreshToken);
        static::assertSame($userId, $externalAuthUser->userId);
        static::assertSame($refreshToken, $externalAuthUser->refreshToken);
        static::assertFalse($externalAuthUser->isNew);
        static::assertSame($email, $externalAuthUser->email);

        $tokenUserData = $this->getTokenUserData($subject);
        static::assertSame($refreshToken, $tokenUserData['refresh_token']);
        static::assertSame($subject, $tokenUserData['user_sub']);
        static::assertSame($userId, Uuid::fromBytesToHex($tokenUserData['user_id']));
    }

    public function testGetUserRateLimiterThrowException(): void
    {
        $refreshToken = Uuid::randomHex();
        $idToken = (new FakeTokenGenerator())->setEmail('not@set.na')->generate();

        $rateLimiter = $this->createRateLimiter();

        $counter = 0;
        try {
            while ($counter <= 10) {
                $this->createUserService($rateLimiter)->getUser($idToken, $refreshToken);
                ++$counter;
            }
        } catch (LoginException $exception) {
            static::assertSame('Wait for 10 seconds', $exception->getMessage());
            static::assertSame(LoginException::LOGIN_RATE_LIMIT_EXCEEDED, $exception->getErrorCode());
            static::assertSame(Response::HTTP_UNAUTHORIZED, $exception->getStatusCode());

            return;
        }

        static::fail('LoginException was not thrown');
    }

    private function createUserService(?RateLimiter $rateLimiter = null): UserService
    {
        $connection = $this->getContainer()->get(Connection::class);
        if ($rateLimiter instanceof RateLimiter) {
            return new UserService($connection, $rateLimiter);
        }

        return new UserService(
            $connection,
            $this->createMock(RateLimiter::class)
        );
    }

    /**
     * @return array{id: string, user_id: string, user_sub: string, refresh_token: string, expiry: string}|null
     */
    private function getTokenUserData(string $subject): ?array
    {
        $connection = $this->getContainer()->get(Connection::class);

        $result = $connection->createQueryBuilder()
            ->select(['id', 'user_id', 'user_sub', 'refresh_token', 'expiry'])
            ->from('token_user')
            ->where('user_sub = :subject')
            ->setParameter('subject', $subject)
            ->executeQuery()
            ->fetchAssociative();

        if (!\is_array($result)) {
            return null;
        }

        return $result;
    }

    private function createRateLimiter(): RateLimiter
    {
        $rateLimiterFactory = new RateLimiterFactory(
            ['enabled' => true,
                'policy' => 'time_backoff',
                'reset' => '24 hours',
                'limits' => [
                    ['limit' => 10, 'interval' => '10 seconds'],
                    ['limit' => 15, 'interval' => '30 seconds'],
                    ['limit' => 20, 'interval' => '60 seconds'],
                ],
                'lock_factory' => 'lock.factory',
                'cache_pool' => 'cache.rate_limiter',
                'id' => 'oauth',
            ],
            new CacheStorage(new ArrayAdapter()),
            $this->getContainer()->get(SystemConfigService::class),
            new LockFactory(new NullStore())
        );
        $rateLimiter = new RateLimiter();
        $rateLimiter->registerLimiterFactory(RateLimiter::OAUTH, $rateLimiterFactory);

        return $rateLimiter;
    }
}
