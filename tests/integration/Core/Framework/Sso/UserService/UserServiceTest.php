<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Sso\UserService;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\SsoException;
use Shopware\Core\Framework\Sso\TokenService\TokenResult;
use Shopware\Core\Framework\Sso\UserService\ExternalAuthUser;
use Shopware\Core\Framework\Sso\UserService\Token;
use Shopware\Core\Framework\Sso\UserService\UserService;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserEntity;
use Shopware\Tests\Integration\Core\Framework\Sso\Helper\FakeTokenGenerator;
use Shopware\Tests\Integration\Core\Framework\Sso\Helper\FakeUserInstaller;
use Shopware\Tests\Integration\Core\Framework\Sso\Helper\ValidUserServiceCreator;
use Shopware\Tests\Unit\Core\Framework\Sso\TokenService\_fixtures\JwksIds;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UserService::class)]
class UserServiceTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testGetAndUpdateUserByExternalTokenWithoutTokenUser(): void
    {
        $userId = Uuid::randomHex();
        $email = 'test@email.com';
        $subject = Uuid::randomHex();

        $fakeUserInstaller = new FakeUserInstaller($this->getContainer()->get(Connection::class));
        $fakeUserInstaller->installBaseUserData($userId, $email);

        $idToken = (new FakeTokenGenerator())->setEmail($email)->setSubject($subject)->generate(JwksIds::KEY_ID_ONE);
        $token = Uuid::randomHex();
        $refreshToken = Uuid::randomHex();

        $tokenResult = TokenResult::createFromResponse(\json_encode([
            'id_token' => $idToken,
            'access_token' => $token,
            'refresh_token' => $refreshToken,
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            'scope' => 'any',
        ], \JSON_THROW_ON_ERROR));

        $externalAuthUser = $this->createUserService()->getAndUpdateUserByExternalToken($tokenResult);
        static::assertSame($userId, $externalAuthUser->userId);
        static::assertSame($refreshToken, $externalAuthUser->token?->refreshToken);
        static::assertSame($email, $externalAuthUser->email);

        // ensure data is created and updated
        $tokenUserData = $this->getTokenUserData($subject);
        static::assertIsArray($tokenUserData);
        static::assertArrayHasKey('token', $tokenUserData);
        static::assertArrayHasKey('user_sub', $tokenUserData);
        static::assertArrayHasKey('user_id', $tokenUserData);

        $tokenObject = $tokenUserData['token'];
        static::assertInstanceOf(Token::class, $tokenObject);

        static::assertSame($token, $tokenObject->token);
        static::assertSame($refreshToken, $tokenObject->refreshToken);
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

    public function testGetAndUpdateUserByExternalTokenWithTokenUser(): void
    {
        $userId = Uuid::randomHex();
        $email = 'anotherFake@email.com';
        $subject = Uuid::randomHex();

        $fakeUserInstaller = new FakeUserInstaller($this->getContainer()->get(Connection::class));
        $fakeUserInstaller->installBaseUserData($userId, $email);
        $fakeUserInstaller->installTokenUser($userId, $subject);

        $idToken = (new FakeTokenGenerator())->setEmail($email)->setSubject($subject)->generate(JwksIds::KEY_ID_ONE);
        $token = Uuid::randomHex();
        $refreshToken = Uuid::randomHex();

        $tokenResult = TokenResult::createFromResponse(\json_encode([
            'id_token' => $idToken,
            'access_token' => $token,
            'refresh_token' => $refreshToken,
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            'scope' => 'any',
        ], \JSON_THROW_ON_ERROR));

        $externalAuthUser = $this->createUserService()->getAndUpdateUserByExternalToken($tokenResult);
        static::assertSame($userId, $externalAuthUser->userId);
        static::assertSame($token, $externalAuthUser->token?->token);
        static::assertSame($refreshToken, $externalAuthUser->token->refreshToken);
        static::assertSame($email, $externalAuthUser->email);

        $tokenUserData = $this->getTokenUserData($subject);
        static::assertIsArray($tokenUserData);
        static::assertArrayHasKey('token', $tokenUserData);
        static::assertArrayHasKey('user_sub', $tokenUserData);
        static::assertArrayHasKey('user_id', $tokenUserData);

        $tokenObject = $tokenUserData['token'];
        static::assertInstanceOf(Token::class, $tokenObject);

        static::assertSame($token, $tokenObject->token);
        static::assertSame($refreshToken, $tokenObject->refreshToken);
        static::assertSame($subject, $tokenUserData['user_sub']);
        static::assertSame($userId, Uuid::fromBytesToHex($tokenUserData['user_id']));
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
        $token = Uuid::randomHex();
        $refreshToken = Uuid::randomHex();

        $tokenResult = TokenResult::createFromResponse(\json_encode([
            'id_token' => $idToken,
            'access_token' => $token,
            'refresh_token' => $refreshToken,
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            'scope' => 'any',
        ], \JSON_THROW_ON_ERROR));

        $externalAuthUser = $this->createUserService()->getAndUpdateUserByExternalToken($tokenResult);
        static::assertSame($userId, $externalAuthUser->userId);
        static::assertSame($token, $externalAuthUser->token?->token);
        static::assertSame($refreshToken, $externalAuthUser->token->refreshToken);
        static::assertSame($localeEmail, $externalAuthUser->email);

        $user = $this->getContainer()->get('user.repository')->search(new Criteria([$userId]), Context::createDefaultContext())->first();
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertSame($tokenEmail, $user->getEmail());
    }

    public function testGetRefreshedExternalTokenForUserWithoutAuthUserShouldThrowException(): void
    {
        $userService = $this->createUserService();

        $this->expectExceptionObject(SsoException::tokenNotFound());

        $userService->getRefreshedExternalTokenForUser(Uuid::randomHex());
    }

    public function testGetRefreshedExternalTokenForUserWithoutTokenShouldThrowException(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $userId = Uuid::randomHex();
        $fakeUserInstaller = new FakeUserInstaller($connection);
        $fakeUserInstaller->installBaseUserData($userId, 'test@test.com');
        $fakeUserInstaller->installTokenUser($userId, Uuid::randomHex());

        $connection->update('oauth_user', ['token' => null], ['user_id' => Uuid::fromHexToBytes($userId)]);

        $userService = $this->createUserService();

        $this->expectExceptionObject(SsoException::tokenNotFound());

        $userService->getRefreshedExternalTokenForUser($userId);
    }

    public function testGetRefreshedExternalTokenForUser(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $userId = Uuid::randomHex();
        $fakeUserInstaller = new FakeUserInstaller($connection);
        $fakeUserInstaller->installBaseUserData($userId, 'test@test.com');
        $fakeUserInstaller->installTokenUser($userId, Uuid::randomHex());

        $userService = $this->createUserService();

        $result = $userService->getRefreshedExternalTokenForUser($userId);

        static::assertSame('access_token', $result);
    }

    public function testRemoveExternalToken(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $userId = Uuid::randomHex();
        $fakeUserInstaller = new FakeUserInstaller($connection);
        $fakeUserInstaller->installBaseUserData($userId, 'test@test.com');
        $fakeUserInstaller->installTokenUser($userId, Uuid::randomHex());

        $testSQL = 'SELECT `token` FROM `oauth_user` WHERE `user_id` = ?;';

        $check = $connection->executeQuery($testSQL, [Uuid::fromHexToBytes($userId)])->fetchOne();
        static::assertSame('{"token": "invalid", "refreshToken": "invalid"}', $check);

        $userService = $this->createUserService();
        $userService->removeExternalToken($userId);

        $result = $connection->executeQuery($testSQL, [Uuid::fromHexToBytes($userId)])->fetchOne();
        static::assertNull($result);
    }

    public function testSearchOAuthUserByUserIdShouldBeNull(): void
    {
        $userService = $this->createUserService();

        $result = $userService->searchOAuthUserByUserId(Uuid::randomHex());

        static::assertNull($result);
    }

    public function testSearchOAuthUserByUserId(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $userId = Uuid::randomHex();
        $fakeUserInstaller = new FakeUserInstaller($connection);
        $fakeUserInstaller->installBaseUserData($userId, 'test@test.com');
        $fakeUserInstaller->installTokenUser($userId, Uuid::randomHex());

        $userService = $this->createUserService();

        $result = $userService->searchOAuthUserByUserId($userId);

        static::assertInstanceOf(ExternalAuthUser::class, $result);
        static::assertSame($userId, $result->userId);
    }

    public function testUpdateOAuthUserWithNewToken(): void
    {
        $id = Uuid::randomHex();
        $userId = Uuid::randomHex();
        $userSub = Uuid::randomHex();
        $email = 'test@example.com';
        $expiryInPast = new \DateTimeImmutable('1970-01-01 00:00:00');

        $baseUser = ExternalAuthUser::create([
            'id' => $id,
            'user_id' => $userId,
            'user_sub' => $userSub,
            'token' => [
                'token' => 'any',
                'refreshToken' => 'any',
            ],
            'expiry' => $expiryInPast,
            'email' => $email,
        ]);

        $tokenResult = TokenResult::createFromResponse(json_encode([
            'id_token' => 'newIdToken',
            'access_token' => 'newAccessToken',
            'refresh_token' => 'newRefreshToken',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            'scope' => 'write',
        ], \JSON_THROW_ON_ERROR));

        $userService = $this->createUserService();
        $result = $userService->updateOAuthUserWithNewToken($baseUser, $tokenResult);

        static::assertSame($id, $result->id);
        static::assertSame($userId, $result->userId);
        static::assertSame($userSub, $result->sub);
        static::assertInstanceOf(Token::class, $result->token);
        static::assertSame('newAccessToken', $result->token->token);
        static::assertSame('newRefreshToken', $result->token->refreshToken);
        static::assertInstanceOf(\DateTimeImmutable::class, $result->expiry);
        static::assertGreaterThan(new \DateTimeImmutable(), $result->expiry);
    }

    public function testSaveOAuthUser(): void
    {
        $userId = Uuid::randomHex();
        $expiry = (new \DateTimeImmutable())->add(new \DateInterval('PT1H'));

        $fakeUserInstaller = new FakeUserInstaller($this->getContainer()->get(Connection::class));
        $fakeUserInstaller->installBaseUserData($userId, 'foo@bar.baz');
        $fakeUserInstaller->installTokenUser($userId, Uuid::randomHex());

        $userService = $this->createUserService();
        $savedUser = $userService->searchOAuthUserByUserId($userId);
        static::assertInstanceOf(ExternalAuthUser::class, $savedUser);

        $user = ExternalAuthUser::create([
            'id' => $savedUser->id,
            'user_id' => $userId,
            'user_sub' => Uuid::randomHex(),
            'token' => [
                'token' => 'newToken',
                'refreshToken' => 'newRefreshToken',
            ],
            'expiry' => $expiry,
            'email' => 'foo@bar.baz',
        ]);

        $userService = $this->createUserService();
        $userService->saveOAuthUser($user);

        $result = $userService->searchOAuthUserByUserId($userId);
        static::assertInstanceOf(ExternalAuthUser::class, $result);
        static::assertSame($user->id, $result->id);
        static::assertSame($user->userId, $result->userId);
        static::assertInstanceOf(Token::class, $result->token);
        static::assertSame('newToken', $result->token->token);
        static::assertSame('newRefreshToken', $result->token->refreshToken);
        static::assertInstanceOf(\DateTimeImmutable::class, $result->expiry);
        static::assertGreaterThan(new \DateTimeImmutable(), $result->expiry);
    }

    private function createUserService(): UserService
    {
        return (new ValidUserServiceCreator(static::class))->create();
    }

    /**
     * @return array{id: string, user_id: string, user_sub: string, token: Token, expiry: string}|null
     */
    private function getTokenUserData(string $subject): ?array
    {
        $connection = $this->getContainer()->get(Connection::class);

        $result = $connection->createQueryBuilder()
            ->select('id', 'user_id', 'user_sub', 'token', 'expiry')
            ->from('oauth_user')
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
        static::assertArrayHasKey('token', $result);
        static::assertArrayHasKey('expiry', $result);

        $result['token'] = Token::fromArray(\json_decode($result['token'], true));

        return $result;
    }
}
