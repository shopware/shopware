/**
 * @sw-package framework
 *
 * Helpers keep deprecation definitions readable while colocating the default
 * metadata and ESLint check and fix behavior for each helper.
 */

import type { DeprecationUsage, FixLevel, MigrationTransform, MigrationTransformContext } from './types';
import { migration, usage, withoutUndefined } from './shared';
export { reference } from './shared';
export type {
    ComponentUsageRuleApi,
    DeprecationMigration,
    DeprecationReference,
    DeprecationUsage,
    FixLevel,
    MigrationTransform,
    MigrationTransformContext,
    MigrationTransformResult,
} from './types';
export { renameProp } from './rename-prop';
export { removeProp } from './remove-prop';
export { mapPropValue } from './map-prop-value';
export { mapOptionsPropKeys } from './map-options-prop-keys';
export { removeSlot } from './remove-slot';
export { slotToProp } from './slot-to-prop';
export { missingProp } from './missing-prop';
export { slotToItemsProp } from './slot-to-items-prop';
export { slotToPropComment } from './slot-to-prop-comment';
export { renameEvent } from './rename-event';
export { removeEvent } from './remove-event';
export { renameVModelArgument } from './rename-v-model-argument';

/**
 * Limits a usage codemod to elements the codemod just converted from the deprecated component,
 * recognised by the "Converted from <component>" comment the conversion puts above them. Use it for
 * steps that carry the old component's behaviour over, so correct usage of the replacement stays unreported.
 *
 * @example
 * onlyAfterConversion(missingProp({ prop: 'size', value: '24px' }))
 */
export function onlyAfterConversion(usageConfig: DeprecationUsage): DeprecationUsage {
    return { ...usageConfig, onlyAfterConversion: true };
}

export const componentMigration = migration;
export const globalApiMigration = migration;
export const jsApiMigration = migration;
export const assetMigration = migration;
export const templateBlockMigration = migration;
export const templateEventMigration = migration;
export const snippetKeyMigration = migration;
export const packageMigration = migration;

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

/**
 * Marks an asset file as deprecated without a replacement; the image-deprecation vite plugin
 * warns on every import of it. Use `replaceExtension` instead when a converted file exists.
 *
 * @example
 * usage: [removeAsset()]
 */
export function removeAsset({
    fix = 'manual',
    message,
}: {
    fix?: FixLevel;
    message?: string;
} = {}): DeprecationUsage {
    return usage('remove-asset', { to: null, fix, message });
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
    return context?.hasObjectVBind === true;
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
        if (context.valueKind === 'expression') {
            return {
                kind: 'replace-with-static-value',
                fix: 'manual',
                value,
                message: `Expression-bound prop values can be false at runtime. Review the expression and replace it with "${value}" manually if needed.`,
            };
        }

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

export const routerLinkToClickTransform: MigrationTransform = (context = {}) => {
    if (usesObjectVBind(context)) {
        return {
            kind: 'router-link-to-click',
            fix: 'manual',
            message: 'Object v-bind can hide router-link usage. Review the bound object and migrate navigation manually.',
        };
    }

    return {
        kind: 'router-link-to-click',
        fix: 'unsafe-auto',
    };
};

export const aiBadgeToTitleSlotTransform: MigrationTransform = (context = {}) => {
    if (usesObjectVBind(context)) {
        return {
            kind: 'ai-badge-to-title-slot',
            fix: 'manual',
            message:
                'Object v-bind can hide ai-badge usage. Review the bound object and add the title slot manually if needed.',
        };
    }

    return {
        kind: 'ai-badge-to-title-slot',
        fix: 'unsafe-auto',
    };
};
