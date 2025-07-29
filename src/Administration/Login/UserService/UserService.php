<?php declare(strict_types=1);

namespace Shopware\Administration\Login\UserService;

use Doctrine\DBAL\Connection;
use Shopware\Administration\Login\LoginException;
use Shopware\Administration\Login\TokenService\IdTokenParser;
use Shopware\Administration\Login\TokenService\ParsedIdToken;
use Shopware\Administration\Login\TokenService\TokenResult;
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

        return ExternalAuthUser::createFromDatabaseQuery($tokenUserData, $tokenResult->accessToken, $tokenResult->refreshToken);
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

        return ExternalAuthUser::create([
            'id' => Uuid::randomHex(),
            'user_id' => $user->getId(),
            'user_sub' => $parsedToken->sub,
            'token' => [
                'token' => $tokenResult->accessToken,
                'refreshToken' => $tokenResult->refreshToken,
            ],
            'expiry' => $parsedToken->expiry,
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
                    'expiry' => $userSearchResult->expiry?->format(\DATE_RFC3339),
                    'created_at' => (new \DateTime())->format(\DATE_RFC3339),
                    'updated_at' => null,
                ],
            );

            return;
        }

        $this->connection->update(
            'oauth_user',
            [
                'token' => \json_encode($userSearchResult->token, \JSON_THROW_ON_ERROR),
                'expiry' => $userSearchResult->expiry?->format(\DATE_RFC3339),
                'updated_at' => (new \DateTime())->format(\DATE_RFC3339),
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
