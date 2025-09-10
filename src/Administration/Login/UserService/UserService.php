<?php declare(strict_types=1);

namespace Shopware\Administration\Login\UserService;

use Doctrine\DBAL\Connection;
use Shopware\Administration\Login\LoginException;
use Shopware\Administration\Login\TokenService\IdTokenParser;
use Shopware\Administration\Login\TokenService\ParsedIdToken;
use Shopware\Administration\Login\TokenService\TokenResult;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\System\User\UserEntity;

/**
 * @internal
 */
#[Package('framework')]
final readonly class UserService
{
    /**
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(
        private Connection $connection,
        private IdTokenParser $idTokenParser,
        private EntityRepository $userRepository,
    ) {
    }

    public function getAndUpdateUser(TokenResult $tokenResult): ExternalAuthUser
    {
        $context = Context::createDefaultContext();
        $parsedIdToken = $this->idTokenParser->parse($tokenResult->idToken);

        $invitedUser = $this->getInvitedUser($context, $parsedIdToken);
        if ($invitedUser instanceof UserEntity) {
            $this->activateInvitedUser($context, $invitedUser, $parsedIdToken);
        }

        $userSearchResult = $this->searchUser($context, $parsedIdToken, $tokenResult);
        if (!$userSearchResult instanceof ExternalAuthUser) {
            throw LoginException::userNotFound($parsedIdToken->email);
        }

        if ($userSearchResult->email !== $parsedIdToken->email) {
            $this->updateUser($context, $userSearchResult->userId, $parsedIdToken->email);
        }

        $this->updateTokenUser($userSearchResult);

        return $userSearchResult;
    }

    public function getRefreshToken(string $userId): ?string
    {
        $tokenString = $this->connection->createQueryBuilder()
            ->select('oauth_user.token')
            ->from('oauth_user', 'oauth_user')
            ->where('oauth_user.user_id = :userId')
            ->setParameter('userId', Uuid::fromHexToBytes($userId))
            ->executeQuery()
            ->fetchOne();

        if (!\is_string($tokenString)) {
            return null;
        }

        $token = \json_decode($tokenString, true);
        if (!\is_array($token) || !\array_key_exists('refreshToken', $token)) {
            return null;
        }

        return $token['refreshToken'];
    }

    public function updateUserToken(string $userId, TokenResult $tokenResult): void
    {
        $expiry = (new \DateTimeImmutable())->add(new \DateInterval('PT' . $tokenResult->expiresIn . 'S'));

        $this->connection->update(
            'oauth_user',
            [
                'token' => json_encode(['token' => $tokenResult->accessToken, 'refreshToken' => $tokenResult->refreshToken]),
                'expiry' => $expiry->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'updated_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            ['user_id' => Uuid::fromHexToBytes($userId)]
        );
    }

    public function removeToken(string $userId): void
    {
        $this->connection->createQueryBuilder()
            ->update('oauth_user')
            ->set('token', ':token')
            ->where('user_id = :userId')
            ->setParameter('userId', Uuid::fromHexToBytes($userId))
            ->setParameter('token', null)
            ->executeQuery();
    }

    private function searchUser(Context $context, ParsedIdToken $parsedToken, TokenResult $tokenResult): ?ExternalAuthUser
    {
        $userSearchResult = $this->searchBySub($parsedToken, $tokenResult);
        if (!$userSearchResult instanceof ExternalAuthUser) {
            $userSearchResult = $this->searchByEmail($context, $parsedToken, $tokenResult);
        }

        return $userSearchResult;
    }

    private function searchBySub(ParsedIdToken $parsedToken, TokenResult $tokenResult): ?ExternalAuthUser
    {
        $tokenUserData = $this->connection->createQueryBuilder()
            ->select('oauth_user.id', 'oauth_user.user_id', 'oauth_user.user_sub', 'oauth_user.token', 'oauth_user.expiry', 'user.email')
            ->from('oauth_user', 'oauth_user')
            ->join('oauth_user', 'user', 'user', 'oauth_user.user_id = user.id')
            ->where('oauth_user.user_sub = :sub')
            ->setParameter('sub', $parsedToken->sub)
            ->executeQuery()
            ->fetchAssociative();

        if (!$tokenUserData) {
            return null;
        }

        $expiry = (new \DateTimeImmutable())->add(new \DateInterval('PT' . $tokenResult->expiresIn . 'S'));

        return ExternalAuthUser::createFromDatabaseQuery($tokenUserData, $tokenResult->accessToken, $tokenResult->refreshToken, $expiry);
    }

    private function searchByEmail(Context $context, ParsedIdToken $parsedToken, TokenResult $tokenResult): ?ExternalAuthUser
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new EqualsFilter('email', $parsedToken->email),
                    new EqualsFilter('active', true),
                ]
            )
        );

        $user = $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($criteria) {
            return $this->userRepository->search($criteria, $context)->first();
        });

        if (!$user instanceof UserEntity) {
            return null;
        }

        $expiry = (new \DateTimeImmutable())->add(new \DateInterval('PT' . $tokenResult->expiresIn . 'S'));

        return ExternalAuthUser::create([
            'id' => Uuid::randomHex(),
            'user_id' => $user->getId(),
            'user_sub' => $parsedToken->sub,
            'token' => [
                'token' => $tokenResult->accessToken,
                'refreshToken' => $tokenResult->refreshToken,
            ],
            'expiry' => $expiry,
            'email' => $user->getEmail(),
            'is_new' => true,
        ]);
    }

    private function updateUser(Context $context, string $userId, string $newMail): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($userId, $newMail): void {
            $this->userRepository->update([
                [
                    'id' => $userId,
                    'email' => $newMail,
                ],
            ], $context);
        });
    }

    private function updateTokenUser(ExternalAuthUser $userSearchResult): void
    {
        if ($userSearchResult->isNew) {
            $this->connection->insert(
                'oauth_user',
                [
                    'id' => Uuid::randomBytes(),
                    'user_id' => Uuid::fromHexToBytes($userSearchResult->userId),
                    'user_sub' => $userSearchResult->sub,
                    'token' => \json_encode($userSearchResult->token, \JSON_THROW_ON_ERROR),
                    'expiry' => $userSearchResult->expiry?->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'updated_at' => null,
                ],
            );

            return;
        }

        $this->connection->update(
            'oauth_user',
            [
                'token' => \json_encode($userSearchResult->token, \JSON_THROW_ON_ERROR),
                'expiry' => $userSearchResult->expiry?->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'updated_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            ['id' => Uuid::fromHexToBytes($userSearchResult->id)]
        );
    }

    private function getInvitedUser(Context $context, ParsedIdToken $parsedToken): ?UserEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new EqualsFilter('email', $parsedToken->email),
                    new EqualsFilter('username', $parsedToken->email),
                    new EqualsFilter('firstName', $parsedToken->email),
                    new EqualsFilter('lastName', $parsedToken->email),
                    new EqualsFilter('active', false),
                ]
            )
        );

        return $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($criteria) {
            return $this->userRepository->search($criteria, $context)->first();
        });
    }

    private function activateInvitedUser(Context $context, UserEntity $userEntity, ParsedIdToken $parsedIdToken): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($userEntity, $parsedIdToken): void {
            $this->userRepository->update([[
                'id' => $userEntity->getId(),
                'active' => true,
                'firstName' => $parsedIdToken->givenName,
                'lastName' => $parsedIdToken->familyName,
                'username' => $parsedIdToken->username,
            ]], $context);
        });
    }
}
