<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\OAuth\Verifier;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Exception\OAuthServerException;
use Shopware\Core\Framework\AdminAuth\MethodSettingsService;
use Shopware\Core\Framework\Api\OAuth\UserRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Verifies the classic username/password first factor.
 *
 * The timing-safe credential check is intentionally replicated from
 * {@see UserRepository} so we keep the same protection
 * against user-enumeration timing attacks (always run password_verify, even for unknown users)
 * without depending on the password-grant wiring (which needs a ClientEntityInterface).
 *
 * @internal
 */
#[Package('framework')]
class PasswordVerifier implements PrimaryVerifierInterface
{
    /**
     * Bcrypt hash for a static dummy password used to equalize timing when no user is found.
     *
     * @see UserRepository::DUMMY_PASSWORD_HASH
     */
    private const DUMMY_PASSWORD_HASH = '$2y$12$PVcA5R6ri9kS.7FnFUBRIOLwqU//bCicx5RFxwecAAccbmZ7V7PKu';

    public function __construct(
        private readonly Connection $connection,
        private readonly MethodSettingsService $methodSettings,
    ) {
    }

    public function supports(string $method): bool
    {
        return $method === 'password';
    }

    public function verifyPrimary(array $payload): string
    {
        if (!$this->methodSettings->isPrimary('password')) {
            throw OAuthServerException::accessDenied('Password login is disabled.');
        }

        $username = $payload['username'] ?? null;
        $password = $payload['password'] ?? null;

        if (!\is_string($username) || $username === '' || !\is_string($password)) {
            throw OAuthServerException::invalidCredentials();
        }

        $user = $this->connection->createQueryBuilder()
            ->select('user.id', 'user.password')
            ->from('user')
            ->where('username = :username')
            ->setParameter('username', $username)
            ->fetchAssociative();

        if (!$user) {
            // Prevent user enumeration via timing attacks by always running password_verify().
            $user = ['password' => self::DUMMY_PASSWORD_HASH];
            $password = 'invalid-password-will-always-fail';
        }

        if (!password_verify($password, (string) $user['password'])) {
            throw OAuthServerException::invalidCredentials();
        }

        return Uuid::fromBytesToHex($user['id']);
    }
}
