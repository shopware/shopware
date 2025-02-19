<?php declare(strict_types=1);

namespace Shopware\Administration\Login\UserService;

use Doctrine\DBAL\Connection;
use Shopware\Administration\Login\TokenService\ParsedIdToken;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
final class UserService
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param non-empty-string $idToken
     */
    public function getUser(string $idToken, string $refreshToken): ?ExternalAuthUser
    {
        $parsedIdToken = ParsedIdToken::createFromIdToken($idToken);

        $userSearchResult = $this->searchUser($parsedIdToken, $refreshToken);
        if (!$userSearchResult instanceof ExternalAuthUser) {
            return null;
        }

        if ($userSearchResult->email !== $parsedIdToken->email) {
            $this->updateUser($userSearchResult);
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
        $userData = $this->connection->createQueryBuilder()
            ->select('user.id', 'user.email')
            ->from('user', 'user')
            ->where('user.email = :email')
            ->setParameter('email', $parsedToken->email)
            ->executeQuery()
            ->fetchAssociative();

        if (!$userData) {
            return null;
        }

        return ExternalAuthUser::create([
            'id' => Uuid::randomHex(),
            'user_id' => Uuid::fromBytesToHex($userData['id']),
            'user_sub' => $parsedToken->sub,
            'refresh_token' => $refreshToken,
            'expiry' => $parsedToken->expiry,
            'email' => $userData['email'],
            'is_new' => true,
        ]);
    }

    private function updateUser(ExternalAuthUser $userSearchResult): void
    {
        $this->connection->update(
            'user',
            ['email' => $userSearchResult->email],
            ['id' => Uuid::fromHexToBytes($userSearchResult->userId)]
        );
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
}
