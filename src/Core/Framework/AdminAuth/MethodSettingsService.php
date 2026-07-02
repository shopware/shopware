<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Single source of truth for the four built-in authentication methods (password, totp, webauthn,
 * recovery_codes).
 *
 * Unlike OIDC providers — which are full entities with per-instance secrets — the built-ins are code
 * capabilities. Two configuration layers control them:
 *
 * 1. The static YAML configuration (`shopware.admin_auth.password_login` for the password method,
 *    `shopware.admin_auth.mfa.methods.*` for the MFA methods) is a hard gate: a method disabled
 *    there is off and cannot be re-enabled at runtime.
 * 2. The system config key `core.adminAuth.methods` (one JSON blob, runtime-editable via the admin)
 *    stores on/off state, ordering and role flags beneath that gate.
 *
 * Each method's capabilities are fixed (e.g. password can never be a second factor, recovery codes can
 * never be primary); this service merges the stored settings over those capability defaults so the rest
 * of the backend can ask simple questions like "is totp enabled as a second factor?".
 *
 * @internal
 */
#[Package('framework')]
class MethodSettingsService
{
    private const CONFIG_KEY = 'core.adminAuth.methods';

    /**
     * Capability matrix + sensible defaults. `canBePrimary` / `canBeSecondFactor` are hard limits the
     * UI and backend must respect; `enabled` / `isPrimary` / `isSecondFactor` / `priority` are the
     * defaults applied when nothing is stored yet.
     *
     * @var array<string, array{canBePrimary: bool, canBeSecondFactor: bool, fallback: bool, enabled: bool, isPrimary: bool, isSecondFactor: bool, priority: int}>
     */
    private const METHODS = [
        'password' => [
            'canBePrimary' => true, 'canBeSecondFactor' => false, 'fallback' => false,
            'enabled' => true, 'isPrimary' => true, 'isSecondFactor' => false, 'priority' => 100,
        ],
        'webauthn' => [
            'canBePrimary' => true, 'canBeSecondFactor' => true, 'fallback' => false,
            'enabled' => true, 'isPrimary' => true, 'isSecondFactor' => true, 'priority' => 90,
        ],
        'totp' => [
            'canBePrimary' => false, 'canBeSecondFactor' => true, 'fallback' => false,
            'enabled' => true, 'isPrimary' => false, 'isSecondFactor' => true, 'priority' => 80,
        ],
        'recovery_codes' => [
            'canBePrimary' => false, 'canBeSecondFactor' => true, 'fallback' => true,
            'enabled' => true, 'isPrimary' => false, 'isSecondFactor' => true, 'priority' => 10,
        ],
    ];

    /**
     * @param array<string, bool> $mfaMethodGates the `shopware.admin_auth.mfa.methods` configuration
     */
    public function __construct(
        private readonly SystemConfigService $systemConfig,
        private readonly array $mfaMethodGates = [],
        private readonly bool $passwordLoginEnabled = true,
    ) {
    }

    /**
     * The full, capability-clamped settings for every built-in method, ordered by priority desc.
     *
     * @return list<array{type: string, enabled: bool, isPrimary: bool, isSecondFactor: bool, priority: int, canBePrimary: bool, canBeSecondFactor: bool, fallback: bool}>
     */
    public function all(): array
    {
        $stored = $this->stored();
        $result = [];

        foreach (self::METHODS as $type => $defaults) {
            $override = \is_array($stored[$type] ?? null) ? $stored[$type] : [];

            $result[] = [
                'type' => $type,
                // The YAML gate wins; beneath it, the stored runtime setting applies.
                'enabled' => $this->allowedByConfig($type) && (bool) ($override['enabled'] ?? $defaults['enabled']),
                // A method can only act as a primary / second factor if its capability allows it.
                'isPrimary' => $defaults['canBePrimary'] && (bool) ($override['isPrimary'] ?? $defaults['isPrimary']),
                'isSecondFactor' => $defaults['canBeSecondFactor'] && (bool) ($override['isSecondFactor'] ?? $defaults['isSecondFactor']),
                'priority' => (int) ($override['priority'] ?? $defaults['priority']),
                'canBePrimary' => $defaults['canBePrimary'],
                'canBeSecondFactor' => $defaults['canBeSecondFactor'],
                'fallback' => $defaults['fallback'],
            ];
        }

        usort($result, static fn (array $a, array $b): int => $b['priority'] <=> $a['priority']);

        return $result;
    }

    public function isEnabled(string $type): bool
    {
        return $this->settings($type)['enabled'] ?? false;
    }

    public function isPrimary(string $type): bool
    {
        $settings = $this->settings($type);

        return ($settings['enabled'] ?? false) && ($settings['isPrimary'] ?? false);
    }

    public function isSecondFactor(string $type): bool
    {
        $settings = $this->settings($type);

        return ($settings['enabled'] ?? false) && ($settings['isSecondFactor'] ?? false);
    }

    public function isFallback(string $type): bool
    {
        return self::METHODS[$type]['fallback'] ?? false;
    }

    /**
     * @return list<string> enabled second-factor method types (incl. fallbacks), ordered by priority
     */
    public function enabledSecondFactors(): array
    {
        return array_values(array_map(
            static fn (array $m): string => $m['type'],
            array_filter($this->all(), static fn (array $m): bool => $m['enabled'] && $m['isSecondFactor'])
        ));
    }

    /**
     * Persist new settings (called by the Methods admin tab). Only known keys are stored.
     *
     * @param array<string, array{enabled?: bool, isPrimary?: bool, isSecondFactor?: bool, priority?: int}> $settings
     */
    public function save(array $settings): void
    {
        $clean = [];
        foreach (self::METHODS as $type => $caps) {
            $in = \is_array($settings[$type] ?? null) ? $settings[$type] : [];
            $clean[$type] = [
                'enabled' => (bool) ($in['enabled'] ?? $caps['enabled']),
                'isPrimary' => $caps['canBePrimary'] && (bool) ($in['isPrimary'] ?? $caps['isPrimary']),
                'isSecondFactor' => $caps['canBeSecondFactor'] && (bool) ($in['isSecondFactor'] ?? $caps['isSecondFactor']),
                'priority' => (int) ($in['priority'] ?? $caps['priority']),
            ];
        }

        $this->systemConfig->set(self::CONFIG_KEY, $clean);
    }

    /**
     * The hard YAML gate: `shopware.admin_auth.password_login` for the password method,
     * `shopware.admin_auth.mfa.methods.<type>` for the MFA methods.
     */
    private function allowedByConfig(string $type): bool
    {
        if ($type === 'password') {
            return $this->passwordLoginEnabled;
        }

        return (bool) ($this->mfaMethodGates[$type] ?? true);
    }

    /**
     * @return array{enabled: bool, isPrimary: bool, isSecondFactor: bool, priority: int}|array{}
     */
    private function settings(string $type): array
    {
        foreach ($this->all() as $method) {
            if ($method['type'] === $type) {
                return [
                    'enabled' => $method['enabled'],
                    'isPrimary' => $method['isPrimary'],
                    'isSecondFactor' => $method['isSecondFactor'],
                    'priority' => $method['priority'],
                ];
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        $value = $this->systemConfig->get(self::CONFIG_KEY);

        return \is_array($value) ? $value : [];
    }
}
