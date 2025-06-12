<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Login\UserService;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Login\UserService\UserService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserEntity;
use Shopware\Tests\Integration\Administration\Login\Helper\FakeTokenGenerator;
use Shopware\Tests\Integration\Administration\Login\Helper\FakeUserInstaller;
use Shopware\Tests\Integration\Administration\Login\Helper\ValidUserServiceCreator;
use Shopware\Tests\Unit\Administration\Login\TokenService\_fixtures\JwksIds;

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

        $idToken = (new FakeTokenGenerator())->setEmail($email)->setSubject($subject)->generate(JwksIds::KEY_ID_ONE);
        $refreshToken = Uuid::randomHex();

        $externalAuthUser = $this->createUserService()->getUser($idToken, $refreshToken);
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

        $idToken = (new FakeTokenGenerator())->setEmail($email)->setSubject($subject)->generate(JwksIds::KEY_ID_ONE);
        $refreshToken = Uuid::randomHex();

        $externalAuthUser = $this->createUserService()->getUser($idToken, $refreshToken);
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

        // check user is activated
        $user = $this->getContainer()->get('user.repository')->search(new Criteria([$externalAuthUser->userId]), Context::createDefaultContext())->first();
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertTrue($user->getActive());
        static::assertSame('given_name', $user->getFirstName());
        static::assertSame('family_name', $user->getLastName());
        static::assertSame('preferred_username', $user->getUsername());
    }

    public function testUserEmailIsUpdated(): void
    {
        $tokenEmail = 'token@email.com';
        $localeEmail = 'locale@email.com';

        $userId = Uuid::randomHex();
        $subject = Uuid::randomHex();

        $fakeUserInstaller = new FakeUserInstaller($this->getContainer()->get(Connection::class));
        $fakeUserInstaller->installBaseUserData($userId, $localeEmail);
        $fakeUserInstaller->installTokenUser($userId, $subject);

        $idToken = (new FakeTokenGenerator())->setEmail($tokenEmail)->setSubject($subject)->generate(JwksIds::KEY_ID_ONE);
        $refreshToken = Uuid::randomHex();

        $externalAuthUser = $this->createUserService()->getUser($idToken, $refreshToken);
        static::assertSame($userId, $externalAuthUser->userId);
        static::assertSame($refreshToken, $externalAuthUser->refreshToken);
        static::assertFalse($externalAuthUser->isNew);
        static::assertSame($localeEmail, $externalAuthUser->email);

        $user = $this->getContainer()->get('user.repository')->search(new Criteria([$userId]), Context::createDefaultContext())->first();
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertSame($tokenEmail, $user->getEmail());
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
