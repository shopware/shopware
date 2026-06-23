/**
 * @sw-package framework
 */

type LegacyOptionsApiOverrideConfig = Record<string, unknown>;

const LEGACY_OPTIONS_API_OVERRIDE_KEYS = [
    'data',
    'methods',
    'computed',
    'watch',
    'mixins',
    'inject',
    'components',
    'directives',
    'provide',
    'extends',
    'inheritAttrs',
    'emits',
] as const;

const LEGACY_OPTIONS_API_LIFECYCLE_HOOKS = [
    'beforeCreate',
    'created',
    'beforeMount',
    'mounted',
    'beforeUpdate',
    'updated',
    'beforeUnmount',
    'unmounted',
    'activated',
    'deactivated',
    'errorCaptured',
] as const;

function hasActiveOverrideValue(config: LegacyOptionsApiOverrideConfig, key: string): boolean {
    if (key === 'inheritAttrs') {
        return Object.prototype.hasOwnProperty.call(config, key);
    }

    const value = config[key];

    return Array.isArray(value) ? value.length > 0 : !!value;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function hasConvertibleOptionsApiOverrideContent(config: unknown): boolean {
    if (!config || typeof config !== 'object') {
        return false;
    }

    const overrideConfig = config as LegacyOptionsApiOverrideConfig;
    const hasOptionsApiKeys = LEGACY_OPTIONS_API_OVERRIDE_KEYS.some((key) => {
        return hasActiveOverrideValue(overrideConfig, key);
    });

    const hasLifecycleHooks = LEGACY_OPTIONS_API_LIFECYCLE_HOOKS.some((hook) => {
        return !!overrideConfig[hook];
    });

    return hasOptionsApiKeys || hasLifecycleHooks;
}
