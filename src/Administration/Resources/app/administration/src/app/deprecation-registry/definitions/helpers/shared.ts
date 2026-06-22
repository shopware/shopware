/**
 * @sw-package framework
 */

import type {
    ComponentUsageRuleApi,
    DeprecationDefinition,
    DeprecationMigration,
    DeprecationReference,
    DeprecationUsage,
    FixLevel,
} from './types';

export function withoutUndefined<T>(value: T): T {
    if (Array.isArray(value)) {
        return value.map(withoutUndefined) as T;
    }

    if (!value || typeof value !== 'object') {
        return value;
    }

    return Object.fromEntries(
        Object.entries(value)
            .filter(
                ([
                    ,
                    entryValue,
                ]) => entryValue !== undefined,
            )
            .map(
                ([
                    entryKey,
                    entryValue,
                ]) => [
                    entryKey,
                    withoutUndefined(entryValue),
                ],
            ),
    ) as T;
}

export function defineDeprecations(definition: DeprecationDefinition): DeprecationDefinition {
    return withoutUndefined(definition);
}

export function reference({ type, target }: { type: string; target: string }): DeprecationReference {
    return { type, target };
}

export function migration(config: DeprecationMigration): DeprecationMigration {
    return withoutUndefined(config);
}

export function usage(kind: string, config: Record<string, unknown>): DeprecationUsage {
    return withoutUndefined({
        kind,
        ...config,
    });
}

export function attributeNameToPropName(attributeName: string): string {
    return attributeName.replace(/-([a-z])/g, (match: string, letter: string) => letter.toUpperCase());
}

export function propNameToAttributeName(propName: string): string {
    return propName.replace(/[A-Z]/g, (letter: string) => `-${letter.toLowerCase()}`);
}

export function normalizeFixLevel(value: unknown, fallback: FixLevel): FixLevel {
    if (value === 'auto' || value === 'unsafe-auto' || value === 'manual') {
        return value;
    }

    return fallback;
}

export function runtimePropWasUsed(runtimeProp: unknown, usedProps: Record<string, unknown>): boolean {
    if (typeof runtimeProp !== 'string') {
        return false;
    }

    return [
        runtimeProp,
        propNameToAttributeName(runtimeProp),
    ].some((propName) => Object.prototype.hasOwnProperty.call(usedProps, propName));
}

export function manualUsage(kind: string, config: Record<string, unknown>): DeprecationUsage {
    return usage(kind, {
        ...config,
        fix: config.fix ?? 'manual',
    });
}

export function componentUsageMessage(
    api: ComponentUsageRuleApi,
    usageConfig: DeprecationUsage,
    apiName: unknown,
    additionalMessage?: string,
): string {
    const replacement = usageConfig.to ? ` Use "${usageConfig.to}" instead.` : '';
    const defaultMessage = `[${api.node.name}] The "${apiName}" API is deprecated.${replacement}`;
    const message = typeof usageConfig.message === 'string' ? `[${api.node.name}] ${usageConfig.message}` : defaultMessage;

    return api.appendRegistryContext(additionalMessage ? `${message}\n${additionalMessage}` : message, api.migration);
}

export function usageFixesAutomatically(api: ComponentUsageRuleApi, usageConfig: DeprecationUsage): boolean {
    return !api.isFixDisabled() && usageConfig.fix !== 'manual';
}
