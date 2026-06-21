/**
 * @sw-package framework
 *
 * Helpers keep deprecation definitions readable while still returning plain
 * data objects for ESLint, runtime reporting and codemods.
 */

export type FixLevel = 'auto' | 'unsafe-auto' | 'manual';

export type DeprecationReference = {
    type: string;
    target: string;
};

export type MigrationTransformContext = {
    phase?: 'metadata' | 'fix';
    valueKind?: 'static' | 'expression' | 'object-v-bind' | 'unknown';
    hasObjectVBind?: boolean;
};

export type MigrationTransformResult = {
    kind: string;
    fix: FixLevel;
    message?: string;
    [key: string]: unknown;
};

export type MigrationTransform = (context?: MigrationTransformContext) => MigrationTransformResult;

export type DeprecationUsage = {
    kind?: string;
    fix?: FixLevel;
    message?: string;
    runtimeProp?: string;
    transform?: MigrationTransform;
    [key: string]: unknown;
};

export type DeprecationMigration = {
    id: string;
    deprecatedIn: string;
    removedIn: string;
    description: string;
    references?: DeprecationReference[];
    usage: DeprecationUsage[];
    component?: string;
    replacement?: string;
    handler?: string;
    api?: string;
    files?: string[];
};

export type DeprecationDefinition = {
    componentApiMigrations?: DeprecationMigration[];
    globalApiMigrations?: DeprecationMigration[];
    jsApiMigrations?: DeprecationMigration[];
    assetMigrations?: DeprecationMigration[];
    templateBlockMigrations?: DeprecationMigration[];
    templateEventMigrations?: DeprecationMigration[];
    snippetKeyMigrations?: DeprecationMigration[];
    packageMigrations?: DeprecationMigration[];
};

function withoutUndefined<T>(value: T): T {
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

function migration(config: DeprecationMigration): DeprecationMigration {
    return withoutUndefined(config);
}

export const componentMigration = migration;
export const globalApiMigration = migration;
export const jsApiMigration = migration;
export const assetMigration = migration;
export const templateBlockMigration = migration;
export const templateEventMigration = migration;
export const snippetKeyMigration = migration;
export const packageMigration = migration;

function usage(kind: string, config: Record<string, unknown>): DeprecationUsage {
    return withoutUndefined({
        kind,
        ...config,
    });
}

function attributeNameToPropName(attributeName: string): string {
    return attributeName.replace(/-([a-z])/g, (match: string, letter: string) => letter.toUpperCase());
}

function withRuntimeProp(config: Record<string, unknown>): Record<string, unknown> {
    const propName = config.runtimeProp ?? config.prop ?? config.from;

    if (typeof propName !== 'string') {
        return config;
    }

    return {
        ...config,
        runtimeProp: attributeNameToPropName(propName),
    };
}

function autoUsage(kind: string, config: Record<string, unknown>): DeprecationUsage {
    return usage(kind, {
        ...config,
        fix: config.fix ?? 'auto',
    });
}

function manualUsage(kind: string, config: Record<string, unknown>): DeprecationUsage {
    return usage(kind, {
        ...config,
        fix: config.fix ?? 'manual',
    });
}

export function renameComponent({
    from,
    to,
    fix = 'auto',
    message,
}: {
    from: string;
    to: string;
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    return usage('rename-component', { from, to, fix, message });
}

export function manualComponentReplacement({
    from,
    to,
    fix = 'manual',
    message,
}: {
    from: string;
    to: string;
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    return usage('manual-component-replacement', { from, to, fix, message });
}

export function renameProp(config: Record<string, unknown>): DeprecationUsage {
    return autoUsage('rename-prop', withRuntimeProp(config));
}

export function removeProp(config: Record<string, unknown>): DeprecationUsage {
    return autoUsage('remove-prop', withRuntimeProp(config));
}

export function mapPropValue(config: Record<string, unknown>): DeprecationUsage {
    return autoUsage('map-prop-value', withRuntimeProp(config));
}

export function removeSlot({
    slot,
    fix = 'auto',
    message,
}: {
    slot: string;
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    return usage('remove-slot', { slot, fix, message });
}

export function slotToProp(config: Record<string, unknown>): DeprecationUsage {
    return autoUsage('slot-to-prop', config);
}

export function customUsage({
    name,
    fix = 'manual',
    message,
}: {
    name: string;
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    return usage('custom', { name, fix, message });
}

export function renameEvent({
    from,
    to,
    fix = 'auto',
    message,
}: {
    from: string;
    to: string;
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    return usage('rename-event', { from, to, fix, message });
}

export function removeEvent({
    event,
    fix = 'auto',
    message,
}: {
    event: string;
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    return usage('remove-event', { event, fix, message });
}

export function renameVModelArgument({
    from,
    to = null,
    fix = 'auto',
    message,
}: {
    from: string | null;
    to?: string | null;
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    return usage('rename-v-model-argument', { from, to, fix, message });
}

export function replaceObjectOption({
    from,
    to,
    fix = 'auto',
    message,
}: {
    from: string;
    to: string;
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    return usage('replace-object-option', { from, to, fix, message });
}

export function memberCall({
    from,
    to = null,
    fix = 'manual',
    message,
}: {
    from: string;
    to?: string | null;
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    return usage('member-call', { from, to, fix, message });
}

export function replaceApi({
    from,
    to,
    fix,
    message,
}: {
    from: string;
    to: string;
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    return manualUsage('replace-api', { from, to, fix, message });
}

export function replaceExtension({
    from,
    to,
    fix = 'auto',
    message,
}: {
    from: string[];
    to: string;
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    return usage('replace-extension', { from, to, fix, message });
}

export function replaceTemplateBlock({
    from,
    to,
    fix = 'manual',
    message,
}: {
    from: string;
    to: string;
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    return usage('replace-template-block', { from, to, fix, message });
}

export function removeTemplateBlock({
    from,
    fix = 'manual',
    message,
}: {
    from: string;
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    return usage('remove-template-block', { from, to: null, fix, message });
}

export function renameTemplateBlock({
    from,
    to,
    fix = 'auto',
    message,
}: {
    from: string;
    to: string;
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    return usage('rename-template-block', { from, to, fix, message });
}

export function removeSnippetKey({
    from,
    fix = 'manual',
    message,
}: {
    from: string;
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    return usage('remove-snippet-key', { from, to: null, fix, message });
}

export function renamePackage({
    from,
    to,
    fix = 'auto',
    message,
}: {
    from: string;
    to: string;
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    return usage('rename-package', { from, to, fix, message });
}

export function renameCall({
    from,
    to,
    fix = 'unsafe-auto',
    message,
}: {
    from: string;
    to: string;
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    return usage('rename-call', { from, to, fix, message });
}

export function templateEventUsage({
    component,
    from,
    to,
    fix = 'auto',
    message,
}: {
    component: string;
    from: string;
    to: string;
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    return withoutUndefined({
        component,
        from,
        to,
        fix,
        message,
    });
}

function usesObjectVBind(context?: MigrationTransformContext): boolean {
    return context?.valueKind === 'object-v-bind' || context?.hasObjectVBind === true;
}

export const invertBooleanTransform: MigrationTransform = (context = {}) => {
    if (usesObjectVBind(context)) {
        return {
            kind: 'invert-boolean',
            fix: 'manual',
            message: 'Object v-bind can hide this boolean prop. Review the bound object and invert the value manually.',
        };
    }

    return {
        kind: 'invert-boolean',
        fix: 'auto',
    };
};

export function addBooleanPropTransform({ prop }: { prop: string }): MigrationTransform {
    return (context = {}) => {
        if (usesObjectVBind(context)) {
            return {
                kind: 'add-boolean-prop',
                fix: 'manual',
                prop,
                message: `Object v-bind can hide this prop value. Review the bound object and add "${prop}" manually if needed.`,
            };
        }

        return {
            kind: 'add-boolean-prop',
            fix: 'auto',
            prop,
        };
    };
}

export function replaceWithStaticValueTransform({ value }: { value: string }): MigrationTransform {
    return (context = {}) => {
        if (usesObjectVBind(context)) {
            return {
                kind: 'replace-with-static-value',
                fix: 'manual',
                value,
                message: `Object v-bind can hide this prop value. Review the bound object and replace it with "${value}" manually if needed.`,
            };
        }

        return {
            kind: 'replace-with-static-value',
            fix: 'auto',
            value,
        };
    };
}
