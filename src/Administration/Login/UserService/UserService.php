<?php declare(strict_types=1);

namespace Shopware\Administration\Login\UserService;

use Doctrine\DBAL\Connection;
use Shopware\Administration\Login\Exception\LoginException;
use Shopware\Administration\Login\TokenService\IdTokenParser;
use Shopware\Administration\Login\TokenService\ParsedIdToken;
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
#[Package('after-sales')]
final class UserService
{
    /**
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly IdTokenParser $idTokenParser,
        private readonly EntityRepository $userRepository,
    ) {
    }

    /**
     * @param non-empty-string $idToken
     */
    public function getUser(string $idToken, string $refreshToken): ExternalAuthUser
    {
        $parsedIdToken = $this->idTokenParser->parse($idToken);

        $invitedUser = $this->getInvitedUser($parsedIdToken);
        if ($invitedUser instanceof UserEntity) {
            $this->activateInvitedUser($invitedUser, $parsedIdToken);
        }

        $userSearchResult = $this->searchUser($parsedIdToken, $refreshToken);
        if (!$userSearchResult instanceof ExternalAuthUser) {
            throw LoginException::userNotFound($parsedIdToken->email);
        }

        if ($userSearchResult->email !== $parsedIdToken->email) {
            $this->updateUser($userSearchResult->userId, $parsedIdToken->email);
        }

        $this->updateTokenUser($userSearchResult);

        return $userSearchResult;
    }

    private function searchUser(ParsedIdToken $parsedToken, string $refreshToken): ?ExternalAuthUser
    {
        $userSearchResult = $this->searchBySub($parsedToken, $refreshToken);
        if (!$userSearchResult instanceof ExternalAuthUser) {
            $userSearchResult = $this->searchByEmail($parsedToken, $refreshToken);
        }

        return $userSearchResult;
    }

    private function searchBySub(ParsedIdToken $parsedToken, string $refreshToken): ?ExternalAuthUser
    {
        $tokenUserData = $this->connection->createQueryBuilder()
            ->select('token_user.id', 'token_user.user_id', 'token_user.user_sub', 'token_user.refresh_token', 'token_user.expiry', 'user.email')
            ->from('token_user', 'token_user')
            ->join('token_user', 'user', 'user', 'token_user.user_id = user.id')
            ->where('token_user.user_sub = :sub')
            ->setParameter('sub', $parsedToken->sub)
            ->executeQuery()
            ->fetchAssociative();

        if (!$tokenUserData) {
            return null;
        }

        $tokenUserData['id'] = Uuid::fromBytesToHex($tokenUserData['id']);
        $tokenUserData['user_id'] = Uuid::fromBytesToHex($tokenUserData['user_id']);
        $tokenUserData['refresh_token'] = $refreshToken;
        $tokenUserData['is_new'] = false;
        if ($tokenUserData['expiry'] !== null) {
            $tokenUserData['expiry'] = new \DateTimeImmutable($tokenUserData['expiry']);
        }

        return ExternalAuthUser::create($tokenUserData);
    }

    private function searchByEmail(ParsedIdToken $parsedToken, string $refreshToken): ?ExternalAuthUser
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

        $user = Context::createDefaultContext()->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($criteria) {
            return $this->userRepository->search($criteria, $context)->first();
        });

        if (!$user instanceof UserEntity) {
            return null;
        }

        return ExternalAuthUser::create([
            'id' => Uuid::randomHex(),
            'user_id' => $user->getId(),
            'user_sub' => $parsedToken->sub,
            'refresh_token' => $refreshToken,
            'expiry' => $parsedToken->expiry,
            'email' => $user->getEmail(),
            'is_new' => true,
        ]);
    }

    private function updateUser(string $userId, string $newMail): void
    {
        Context::createDefaultContext()->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($userId, $newMail): void {
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
                'token_user',
                [
                    'id' => Uuid::randomBytes(),
                    'user_id' => Uuid::fromHexToBytes($userSearchResult->userId),
                    'user_sub' => $userSearchResult->sub,
                    'refresh_token' => $userSearchResult->refreshToken,
                    'expiry' => $userSearchResult->expiry?->format(\DATE_RFC3339),
                    'created_at' => (new \DateTime())->format(\DATE_RFC3339),
                    'updated_at' => null,
                ],
            );

            return;
        }

        $this->connection->update(
            'token_user',
            [
                'refresh_token' => $userSearchResult->refreshToken,
                'expiry' => $userSearchResult->expiry?->format(\DATE_RFC3339),
                'updated_at' => (new \DateTime())->format(\DATE_RFC3339),
            ],
            ['id' => Uuid::fromHexToBytes($userSearchResult->id)]
        );
    }

    private function getInvitedUser(ParsedIdToken $parsedToken): ?UserEntity
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

        $user = Context::createDefaultContext()->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($criteria) {
            return $this->userRepository->search($criteria, $context)->first();
        });

        return $user;
    }

    private function activateInvitedUser(UserEntity $userEntity, ParsedIdToken $parsedIdToken): void
    {
        Context::createDefaultContext()->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($userEntity, $parsedIdToken): void {
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
