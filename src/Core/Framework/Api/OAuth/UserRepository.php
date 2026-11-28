<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\OAuth\User\User;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\PasswordHasher\AbstractPasswordHasher;
use Shopware\Core\Framework\Sso\Config\LoginConfigService;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * OAuth integrations should rely on {@see UserRepositoryInterface} instead of this concrete Shopware class.
 */
#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class UserRepository implements UserRepositoryInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly LoginConfigService $loginConfigService,
        private readonly AbstractPasswordHasher $passwordHasher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getUserEntityByUserCredentials(
        string $username,
        #[\SensitiveParameter]
        string $password,
        string $grantType,
        ClientEntityInterface $clientEntity
    ): ?UserEntityInterface {
        if ($this->loginConfigService->getConfig()?->useDefault === false) {
            // never allow login via password if the default login is disabled (e.g. using SSO only)
            return null;
        }

        $builder = $this->connection->createQueryBuilder();
        $user = $builder->select('user.id', 'user.password', 'user.active')
            ->from('user')
            ->where('username = :username')
            ->setParameter('username', $username)
            ->fetchAssociative();

        if (!$user) {
            // Prevent user enumeration via timing attacks
            $this->passwordHasher->verifyPlaceholder($password);

            return null;
        }

        $passwordHash = (string) $user['password'];
        if (!$this->passwordHasher->verify($passwordHash, $password)) {
            return null;
        }

        if (!(bool) $user['active']) {
            return null;
        }

        if ($this->passwordHasher->needsRehash($passwordHash)) {
            $this->rehashPassword($password, (string) $user['id']);
        }

        return new User(Uuid::fromBytesToHex($user['id']));
    }

    /**
     * Replaces an outdated hash after a successful login. Failing to do so must never prevent the login.
     *
     * Written without the DAL on purpose: the password did not change, so no user written event may be dispatched.
     * The `UserCredentialsChangedSubscriber` would revoke all tokens of the user and update `last_updated_password_at` for it.
     * The hash is still created by the configured admin password hasher, the same one the `PasswordFieldSerializer` uses.
     */
    private function rehashPassword(#[\SensitiveParameter] string $password, string $userId): void
    {
        try {
            $this->connection->update(
                'user',
                ['password' => $this->passwordHasher->hash($password)],
                ['id' => $userId],
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Could not rehash the password of user {userId}: {message}', [
                'userId' => Uuid::fromBytesToHex($userId),
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }
}
