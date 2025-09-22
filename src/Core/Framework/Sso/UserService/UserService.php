<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Sso\UserService;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\SsoException;
use Shopware\Core\Framework\Sso\TokenService\ExternalTokenService;
use Shopware\Core\Framework\Sso\TokenService\IdTokenParser;
use Shopware\Core\Framework\Sso\TokenService\ParsedIdToken;
use Shopware\Core\Framework\Sso\TokenService\TokenResult;
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
        private ExternalTokenService $externalTokenService,
    ) {
    }

    public function getAndUpdateUserByExternalToken(TokenResult $tokenResult): ExternalAuthUser
    {
        $context = Context::createDefaultContext();
        $parsedIdToken = $this->idTokenParser->parse($tokenResult->idToken);

        $oAuthUser = $this->searchOAuthUserBySub($parsedIdToken->sub);
        if (!$oAuthUser instanceof ExternalAuthUser) {
            // in this case we don't have an oauth_user yet,
            // we try to look for a user with that mail
            $user = $this->searchUserByEmail($context, $parsedIdToken->email);
            if (!$user instanceof UserEntity) {
                throw SsoException::userNotFound($parsedIdToken->email);
            }

            // if we found a matching one we create the corresponding oauth_user with the new token
            $oAuthUser = ExternalAuthUser::create([
                'id' => Uuid::randomHex(),
                'user_id' => $user->getId(),
                'user_sub' => $parsedIdToken->sub,
                'token' => [
                    'token' => $tokenResult->accessToken,
                    'refreshToken' => $tokenResult->refreshToken,
                ],
                'expiry' => $tokenResult->getExpiryDateTime(),
                'email' => $user->getEmail(),
            ]);
            $this->insertOAuthUser($oAuthUser);

            if ($this->isInvitedUser($user)) {
                // if this user was previously invited, update the user data to reflect that the invite was accepted
                $this->activateInvitedUser($context, $user, $parsedIdToken);
            }

            return $oAuthUser;
        }

        // found an existing oauth_user, update it with new token / data
        if ($oAuthUser->email !== $parsedIdToken->email) {
            $this->updateUserEmail($context, $oAuthUser->userId, $parsedIdToken->email);
        }

        $oAuthUser = $this->updateOAuthUserWithNewToken($oAuthUser, $tokenResult);
        $this->saveOAuthUser($oAuthUser);

        return $oAuthUser;
    }

    public function getRefreshedExternalTokenForUser(string $userId): string
    {
        $oAuthUser = $this->searchOAuthUserByUserId($userId);

        if (!$oAuthUser instanceof ExternalAuthUser || !$oAuthUser->token instanceof Token) {
            throw SsoException::tokenNotFound();
        }

        if ($oAuthUser->expiry >= new \DateTimeImmutable()) {
            return $oAuthUser->token->token;
        }

        // token already expired, try to fetch a new one with the refresh token
        $newOAuthToken = $this->externalTokenService->getUserTokenByRefreshToken($oAuthUser->token->refreshToken);
        $oAuthUser = $this->updateOAuthUserWithNewToken($oAuthUser, $newOAuthToken);
        $this->saveOAuthUser($oAuthUser);

        return $newOAuthToken->accessToken;
    }

    public function removeExternalToken(string $userId): void
    {
        $this->connection->createQueryBuilder()
            ->update('oauth_user')
            ->set('token', ':token')
            ->where('user_id = :userId')
            ->setParameter('userId', Uuid::fromHexToBytes($userId))
            ->setParameter('token', null)
            ->executeQuery();
    }

    public function searchOAuthUserByUserId(string $userId): ?ExternalAuthUser
    {
        $oAuthUserData = $this->connection->createQueryBuilder()
            ->select('oauth_user.id', 'oauth_user.user_id', 'oauth_user.user_sub', 'oauth_user.token', 'oauth_user.expiry', 'user.email')
            ->from('oauth_user', 'oauth_user')
            ->join('oauth_user', 'user', 'user', 'oauth_user.user_id = user.id')
            ->where('oauth_user.user_id = :userId')
            ->setParameter('userId', Uuid::fromHexToBytes($userId))
            ->executeQuery()
            ->fetchAssociative();

        if (!$oAuthUserData) {
            return null;
        }

        return ExternalAuthUser::createFromDatabaseQuery($oAuthUserData);
    }

    public function updateOAuthUserWithNewToken(ExternalAuthUser $externalAuthUser, TokenResult $tokenResult): ExternalAuthUser
    {
        return ExternalAuthUser::create([
            'id' => $externalAuthUser->id,
            'user_id' => $externalAuthUser->userId,
            'user_sub' => $externalAuthUser->sub,
            'token' => [
                'token' => $tokenResult->accessToken,
                'refreshToken' => $tokenResult->refreshToken,
            ],
            'expiry' => $tokenResult->getExpiryDateTime(),
            'email' => $externalAuthUser->email,
        ]);
    }

    public function saveOAuthUser(ExternalAuthUser $userSearchResult): void
    {
        $this->connection->update(
            'oauth_user',
            [
                'token' => \json_encode($userSearchResult->token, \JSON_THROW_ON_ERROR),
                'expiry' => $userSearchResult->expiry->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'updated_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            ['id' => Uuid::fromHexToBytes($userSearchResult->id)]
        );
    }

    private function searchOAuthUserBySub(string $userSubject): ?ExternalAuthUser
    {
        $oAuthUserData = $this->connection->createQueryBuilder()
            ->select('oauth_user.id', 'oauth_user.user_id', 'oauth_user.user_sub', 'oauth_user.token', 'oauth_user.expiry', 'user.email')
            ->from('oauth_user', 'oauth_user')
            ->join('oauth_user', 'user', 'user', 'oauth_user.user_id = user.id')
            ->where('oauth_user.user_sub = :sub')
            ->setParameter('sub', $userSubject)
            ->executeQuery()
            ->fetchAssociative();

        if (!$oAuthUserData) {
            return null;
        }

        return ExternalAuthUser::createFromDatabaseQuery($oAuthUserData);
    }

    private function updateUserEmail(Context $context, string $userId, string $newMail): void
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

    private function insertOAuthUser(ExternalAuthUser $userSearchResult): void
    {
        $this->connection->insert(
            'oauth_user',
            [
                'id' => Uuid::randomBytes(),
                'user_id' => Uuid::fromHexToBytes($userSearchResult->userId),
                'user_sub' => $userSearchResult->sub,
                'token' => \json_encode($userSearchResult->token, \JSON_THROW_ON_ERROR),
                'expiry' => $userSearchResult->expiry->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'updated_at' => null,
            ],
        );
    }

    private function searchUserByEmail(Context $context, string $email): ?UserEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('email', $email),
        );

        return $this->userRepository->search($criteria, $context)->first();
    }

    private function isInvitedUser(UserEntity $user): bool
    {
        return $user->getUsername() === $user->getEmail()
            && $user->getFirstName() === $user->getEmail()
            && $user->getLastName() === $user->getEmail()
            && $user->getActive() === false;
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
