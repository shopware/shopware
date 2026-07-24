import type { ObjectLiteralExpression } from 'ts-morph';
import { SyntaxKind } from 'ts-morph';
import type { LifecycleHook } from './types';

const LIFECYCLE_MAP: Record<string, string> = {
    mounted: 'onMounted',
    beforeMount: 'onBeforeMount',
    beforeUnmount: 'onBeforeUnmount',
    unmounted: 'onUnmounted',
    // Vue 2 legacy names kept for components that haven't fully adopted Vue 3 naming
    beforeDestroy: 'onBeforeUnmount',
    destroyed: 'onUnmounted',
    updated: 'onUpdated',
    beforeUpdate: 'onBeforeUpdate',
    activated: 'onActivated',
    deactivated: 'onDeactivated',
};

const MIGRATABLE_HOOK_NAMES = new Set([
    'created',
    ...Object.keys(LIFECYCLE_MAP),
]);

/**
 * Only method-shorthand hooks (`mounted() {}`) are migrated. Function-valued
 * (`created: function () {}`) or shorthand (`created,`) hooks are dropped by
 * extractLifecycleHooks, so report them as requiring manual migration.
 */
export function analyzeUnsupportedLifecycleHooks(optionsObj: ObjectLiteralExpression): string[] {
    const reasons: string[] = [];

    for (const prop of optionsObj.getProperties()) {
        if (prop.isKind(SyntaxKind.MethodDeclaration)) {
            continue;
        }

        const name = prop.isKind(SyntaxKind.PropertyAssignment)
            ? prop.asKindOrThrow(SyntaxKind.PropertyAssignment).getName()
            : prop.isKind(SyntaxKind.ShorthandPropertyAssignment)
              ? prop.asKindOrThrow(SyntaxKind.ShorthandPropertyAssignment).getName()
              : undefined;

        if (name && MIGRATABLE_HOOK_NAMES.has(name)) {
            reasons.push(`${name}: lifecycle hook must be defined as a method to be migrated`);
        }
    }

    return reasons;
}

export function extractLifecycleHooks(optionsObj: ObjectLiteralExpression): LifecycleHook[] {
    const result: LifecycleHook[] = [];

    for (const prop of optionsObj.getProperties()) {
        // Example: `{ mounted() { this.loadProduct(); } }`
        if (!prop.isKind(SyntaxKind.MethodDeclaration)) continue;

        const method = prop.asKindOrThrow(SyntaxKind.MethodDeclaration);
        const hookName = method.getName();

        // `created` has no Composition API equivalent — its body is emitted directly in setup()
        if (hookName === 'created') {
            result.push({
                hookName,
                compositionName: null,
                bodyText: method.getBodyText() ?? '',
                isAsync: method.isAsync(),
            });
            continue;
        }

        const compositionName = LIFECYCLE_MAP[hookName];
        if (compositionName) {
            result.push({
                hookName,
                compositionName,
                bodyText: method.getBodyText() ?? '',
                isAsync: method.isAsync(),
            });
        }
    }

    return result;
}
