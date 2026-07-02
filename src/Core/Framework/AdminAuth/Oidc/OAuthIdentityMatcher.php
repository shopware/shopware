<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Oidc;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\AdminAuth\AdminAuthException;
use Shopware\Core\Framework\AdminAuth\Entity\OauthIdentity\AdminAuthOauthIdentityCollection;
use Shopware\Core\Framework\AdminAuth\Provider\AdminAuthProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\String\ByteString;

/**
 * Resolves a Shopware admin user from validated OIDC claims.
 *
 * Resolution order:
 *   1. existing identity link for (provider, sub)
 *   2. a user whose email matches the verified id_token email
 *   3. auto-provision a new user from the claims (only when the provider allows it)
 *
 * On a successful match the (provider, sub) -> user link is persisted for next time.
 *
 * @internal
 */
#[Package('framework')]
class OAuthIdentityMatcher
{
    /**
     * @param EntityRepository<AdminAuthOauthIdentityCollection> $identityRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $identityRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return string the resolved user id as a hex string
     */
    public function resolve(AdminAuthProvider $provider, OidcClaims $claims, Context $context): string
    {
        $existingUserId = $this->findLinkedUser($provider->id, $claims->sub);
        if ($existingUserId !== null) {
            $this->touchIdentity($provider->id, $claims, $existingUserId, $context);

            return $existingUserId;
        }

        if ($claims->email === null || !$claims->emailVerified) {
            throw AdminAuthException::oidcLoginFailed('a verified email is required to match or provision an admin user');
        }

        $userId = $this->findUserByEmail($claims->email);

        if ($userId === null) {
            if (!$provider->autoProvision) {
                throw AdminAuthException::oidcLoginFailed('no admin user matches the OIDC email and auto-provisioning is disabled');
            }

            $userId = $this->provisionUser($claims);
        }

        $this->touchIdentity($provider->id, $claims, $userId, $context);

        return $userId;
    }

    private function findLinkedUser(string $providerId, string $sub): ?string
    {
        $userId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(user_id)) FROM admin_auth_oauth_identity WHERE provider_id = :provider AND sub = :sub',
            ['provider' => Uuid::fromHexToBytes($providerId), 'sub' => $sub]
        );

        return $userId === false ? null : (string) $userId;
    }

    private function findUserByEmail(string $email): ?string
    {
        $userId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(id)) FROM `user` WHERE email = :email',
            ['email' => $email]
        );

        return $userId === false ? null : (string) $userId;
    }

    private function provisionUser(OidcClaims $claims): string
    {
        $userId = Uuid::randomBytes();
        $localeId = $this->defaultLocaleId();

        [$firstName, $lastName] = $this->splitName($claims);
        $username = $claims->preferredUsername ?? $claims->email ?? Uuid::fromBytesToHex($userId);

        RetryableQuery::retryable($this->connection, function () use ($userId, $localeId, $username, $firstName, $lastName, $claims): void {
            $this->connection->insert('user', [
                'id' => $userId,
                'locale_id' => $localeId,
                'username' => $username,
                // Random unusable password: the user authenticates via the external provider.
                'password' => password_hash(ByteString::fromRandom(40)->toString(), \PASSWORD_DEFAULT),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $claims->email,
                'active' => 1,
                // Provisioned users currently get the admin flag so they are usable right away.
                // The group -> role synchronization (follow-up) will provision with admin=0 when
                // the provider defines a role mapping.
                'admin' => 1,
                'time_zone' => 'UTC',
                'created_at' => $this->clock->now()->format('Y-m-d H:i:s.v'),
            ]);
        });

        return Uuid::fromBytesToHex($userId);
    }

    private function touchIdentity(string $providerId, OidcClaims $claims, string $userId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('providerId', $providerId));
        $criteria->addFilter(new EqualsFilter('sub', $claims->sub));
        $criteria->setLimit(1);

        $existingId = $this->identityRepository->searchIds($criteria, $context)->firstId();

        $this->identityRepository->upsert([[
            'id' => $existingId ?? Uuid::randomHex(),
            'providerId' => $providerId,
            'userId' => $userId,
            'sub' => $claims->sub,
            'email' => $claims->email,
        ]], $context);
    }

    private function defaultLocaleId(): string
    {
        $localeId = $this->connection->fetchOne(
            'SELECT id FROM locale WHERE code = :code',
            ['code' => 'en-GB']
        );

        if ($localeId === false) {
            $localeId = $this->connection->fetchOne('SELECT id FROM locale LIMIT 1');
        }

        if ($localeId === false) {
            throw AdminAuthException::oidcLoginFailed('no locale available to provision the user');
        }

        return $localeId;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(OidcClaims $claims): array
    {
        $name = $claims->name ?? $claims->preferredUsername ?? $claims->email ?? 'SSO User';
        $parts = explode(' ', trim($name), 2);

        return [$parts[0], $parts[1] ?? $parts[0]];
    }
}
