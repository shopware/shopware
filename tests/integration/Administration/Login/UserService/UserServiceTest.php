<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Login\UserService;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Login\TokenService\TokenResult;
use Shopware\Administration\Login\UserService\Token;
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
#[Package('framework')]
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

        $externalAuthUser = $this->createUserService()->getAndUpdateUser($tokenResult);
        static::assertSame($userId, $externalAuthUser->userId);
        static::assertSame($refreshToken, $externalAuthUser->token->refreshToken);
        static::assertTrue($externalAuthUser->isNew);
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

        $externalAuthUser = $this->createUserService()->getAndUpdateUser($tokenResult);
        static::assertSame($userId, $externalAuthUser->userId);
        static::assertSame($token, $externalAuthUser->token->token);
        static::assertSame($refreshToken, $externalAuthUser->token->refreshToken);
        static::assertFalse($externalAuthUser->isNew);
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

        $externalAuthUser = $this->createUserService()->getAndUpdateUser($tokenResult);
        static::assertSame($userId, $externalAuthUser->userId);
        static::assertSame($token, $externalAuthUser->token->token);
        static::assertSame($refreshToken, $externalAuthUser->token->refreshToken);
        static::assertFalse($externalAuthUser->isNew);
        static::assertSame($localeEmail, $externalAuthUser->email);

        $user = $this->getContainer()->get('user.repository')->search(new Criteria([$userId]), Context::createDefaultContext())->first();
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertSame($tokenEmail, $user->getEmail());
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
