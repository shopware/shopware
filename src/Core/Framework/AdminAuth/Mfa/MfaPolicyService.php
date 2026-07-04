<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Mfa;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\AdminAuth\MethodSettingsService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Decides whether a user must complete a second factor after the primary verification succeeded,
 * and which enrolled factor types can satisfy the challenge.
 *
 * Ported from the FroshAdminAuth plugin, adapted to the `shopware.admin_auth.mfa.required` enum
 * ('none' | 'admins' | 'all'):
 *
 * - Enrolled factors are always honored: a user with at least one active, enabled, non-fallback
 *   second factor gets a challenge on every login, independent of the policy. This is the whole
 *   behavior under 'none'.
 * - 'admins' / 'all' additionally declare that admin-flag users (respectively all users) are
 *   *required* to use MFA — exposed via {@see isEnrollmentRequired()}. Enforced enrollment (blocking
 *   the login or forcing a factor on users who have none) is intentionally left for a later phase,
 *   exactly like the plugin's `requireSecondFactorForAdmins` behavior: a required user without
 *   enrolled factors still logs in without a challenge.
 */
#[Package('framework')]
class MfaPolicyService
{
    public const REQUIRED_NONE = 'none';
    public const REQUIRED_ADMINS = 'admins';
    public const REQUIRED_ALL = 'all';

    /**
     * Default lifetime of a second-factor challenge in seconds.
     */
    public const DEFAULT_CHALLENGE_TTL = 300;

    private const CHALLENGE_TTL_CONFIG_KEY = 'core.adminAuth.mfaChallengeTtlSeconds';

    /**
     * @internal
     *
     * @param 'none'|'admins'|'all' $required the `shopware.admin_auth.mfa.required` configuration
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly SystemConfigService $systemConfig,
        private readonly MethodSettingsService $methodSettings,
        private readonly string $required = self::REQUIRED_NONE,
    ) {
    }

    public function requiresSecondFactor(string $userId): bool
    {
        return $this->nonFallbackFactors($userId) !== [];
    }

    /**
     * Whether the MFA policy demands that this user enrolls a second factor. Not enforced during
     * login yet (see class docblock); meant for the enrollment UI and self-service endpoints.
     */
    public function isEnrollmentRequired(string $userId): bool
    {
        if ($this->required === self::REQUIRED_ALL) {
            return true;
        }

        if ($this->required === self::REQUIRED_ADMINS) {
            return (bool) $this->connection->fetchOne(
                'SELECT admin FROM `user` WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($userId)]
            );
        }

        return false;
    }

    /**
     * @return list<string> the enrolled, active second-factor types that can satisfy the challenge
     *                      (including fallback types, but only when a non-fallback factor also exists)
     */
    public function availableFactors(string $userId): array
    {
        $all = $this->enrolledFactors($userId);

        $nonFallback = array_values(array_filter(
            $all,
            fn (string $type): bool => !$this->methodSettings->isFallback($type)
        ));

        // No real second factor enrolled/enabled → no challenge at all.
        return $nonFallback === [] ? [] : $all;
    }

    public function challengeTtl(): int
    {
        $ttl = (int) $this->systemConfig->get(self::CHALLENGE_TTL_CONFIG_KEY);

        return $ttl > 0 ? $ttl : self::DEFAULT_CHALLENGE_TTL;
    }

    /**
     * @return list<string> active second-factor types excluding fallbacks
     */
    private function nonFallbackFactors(string $userId): array
    {
        return array_values(array_filter(
            $this->enrolledFactors($userId),
            fn (string $type): bool => !$this->methodSettings->isFallback($type)
        ));
    }

    /**
     * Factors the user has enrolled AND that are still enabled as second factors in the method
     * settings. Disabling a method (in the admin or via YAML) removes it as a usable factor here.
     *
     * @return list<string>
     */
    private function enrolledFactors(string $userId): array
    {
        $types = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT type FROM admin_auth_user_method WHERE user_id = :id AND active = 1',
            ['id' => Uuid::fromHexToBytes($userId)]
        );

        return array_values(array_filter(
            array_map('strval', $types),
            fn (string $type): bool => $this->methodSettings->isSecondFactor($type)
        ));
    }
}
