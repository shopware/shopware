<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Login\UserService;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Login\UserService\ExternalAuthUser;
use Shopware\Administration\Login\UserService\UserService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Administration\Login\Helper\FakeTokenGenerator;
use Shopware\Tests\Integration\Administration\Login\Helper\FakeUserInstaller;
use Shopware\Tests\Integration\Administration\Login\Helper\ValidUserServiceCreator;

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

        $idToken = (new FakeTokenGenerator())->setEmail($email)->setSubject($subject)->generate('b16b070d-28e4-4759-9c51-d43730dda8fa');
        $refreshToken = Uuid::randomHex();

        $externalAuthUser = $this->createUserService()->getUser($idToken, $refreshToken);
        static::assertInstanceOf(ExternalAuthUser::class, $externalAuthUser);
        static::assertSame($userId, $externalAuthUser->userId);
        static::assertSame($refreshToken, $externalAuthUser->refreshToken);
        static::assertTrue($externalAuthUser->isNew);
        static::assertSame($email, $externalAuthUser->email);

        // ensure data is created and updated
        $tokenUserData = $this->getTokenUserData($subject);
        static::assertIsArray($tokenUserData);
        static::assertArrayHasKey('refresh_token', $tokenUserData);
        static::assertArrayHasKey('user_sub', $tokenUserData);
        static::assertArrayHasKey('user_id', $tokenUserData);
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

        $idToken = (new FakeTokenGenerator())->setEmail($email)->setSubject($subject)->generate('b16b070d-28e4-4759-9c51-d43730dda8fa');
        $refreshToken = Uuid::randomHex();

        $externalAuthUser = $this->createUserService()->getUser($idToken, $refreshToken);
        static::assertInstanceOf(ExternalAuthUser::class, $externalAuthUser);
        static::assertSame($userId, $externalAuthUser->userId);
        static::assertSame($refreshToken, $externalAuthUser->refreshToken);
        static::assertFalse($externalAuthUser->isNew);
        static::assertSame($email, $externalAuthUser->email);

        $tokenUserData = $this->getTokenUserData($subject);
        static::assertIsArray($tokenUserData);
        static::assertArrayHasKey('refresh_token', $tokenUserData);
        static::assertArrayHasKey('user_sub', $tokenUserData);
        static::assertArrayHasKey('user_id', $tokenUserData);
        static::assertSame($refreshToken, $tokenUserData['refresh_token']);
        static::assertSame($subject, $tokenUserData['user_sub']);
        static::assertSame($userId, Uuid::fromBytesToHex($tokenUserData['user_id']));
    }

    private function createUserService(): UserService
    {
        return (new ValidUserServiceCreator())->create();
    }

    /**
     * @return array{id: string, user_id: string, user_sub: string, refresh_token: string, expiry: string}|null
     */
    private function getTokenUserData(string $subject): ?array
    {
        $connection = $this->getContainer()->get(Connection::class);

        $result = $connection->createQueryBuilder()
            ->select('id', 'user_id', 'user_sub', 'refresh_token', 'expiry')
            ->from('token_user')
            ->where('user_sub = :subject')
            ->setParameter('subject', $subject)
            ->executeQuery()
            ->fetchAssociative();

        if (!\is_array($result)) {
            return null;
        }

        static::assertArrayHasKey('id', $result);
        static::assertArrayHasKey('user_id', $result);
        static::assertArrayHasKey('user_sub', $result);
        static::assertArrayHasKey('refresh_token', $result);
        static::assertArrayHasKey('expiry', $result);

        return $result;
    }
}
