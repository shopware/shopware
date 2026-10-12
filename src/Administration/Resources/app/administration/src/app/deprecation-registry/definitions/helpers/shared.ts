/**
 * @sw-package framework
 */

import type { ComponentUsageRuleApi, DeprecationMigration, DeprecationReference, DeprecationUsage } from './types';

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
    // eslint-disable-next-line @typescript-eslint/no-base-to-string -- registry usage targets are strings
    const replacement = usageConfig.to ? ` Use "${usageConfig.to}" instead.` : '';
    const defaultMessage = `[${api.node.name}] The "${apiName}" API is deprecated.${replacement}`;
    const message = typeof usageConfig.message === 'string' ? `[${api.node.name}] ${usageConfig.message}` : defaultMessage;

    return api.appendRegistryContext(additionalMessage ? `${message}\n${additionalMessage}` : message, api.migration);
}

export function usageFixesAutomatically(api: ComponentUsageRuleApi, usageConfig: DeprecationUsage): boolean {
    return !api.isFixDisabled() && usageConfig.fix !== 'manual';
}

/**
 * Tells whether a component usage binds an object with `v-bind="…"`, which can hide or override any
 * prop, so prop fixes on that element must fall back to a manual review.
 *
 * @example
 * hasObjectVBind(api); // true for <sw-field v-bind="fieldProps" />
 */
export function hasObjectVBind(api: ComponentUsageRuleApi): boolean {
    return findObjectVBindAttribute(api) !== null;
}

/**
 * Finds the object `v-bind="…"` attribute (a `v-bind` without argument) on the component usage's start tag.
 *
 * @example
 * findObjectVBindAttribute(api); // the VAttribute for <sw-field v-bind="fieldProps" />, null without one
 */
export function findObjectVBindAttribute(api: ComponentUsageRuleApi): Record<string, unknown> | null {
    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access -- template nodes are untyped vue-eslint-parser records
    const attributes: Array<Record<string, unknown>> = api.node.startTag.attributes;

    return (
        attributes.find((attribute) => {
            return api.ast.getDirectiveName(attribute) === 'bind' && !api.ast.getDirectiveArgumentName(attribute);
        }) ?? null
    );
}
