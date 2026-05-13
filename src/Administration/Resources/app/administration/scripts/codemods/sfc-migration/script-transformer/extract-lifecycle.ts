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

export function extractLifecycleHooks(optionsObj: ObjectLiteralExpression): LifecycleHook[] {
    const result: LifecycleHook[] = [];

    for (const prop of optionsObj.getProperties()) {
        if (!prop.isKind(SyntaxKind.MethodDeclaration)) continue;

        const method = prop.asKindOrThrow(SyntaxKind.MethodDeclaration);
        const hookName = method.getName();

        // `created` has no Composition API equivalent — its body is emitted directly in setup()
        if (hookName === 'created') {
            result.push({ hookName, compositionName: null, bodyText: method.getBodyText() ?? '' });
            continue;
        }

        const compositionName = LIFECYCLE_MAP[hookName];
        if (compositionName) {
            result.push({ hookName, compositionName, bodyText: method.getBodyText() ?? '' });
        }
    }

    return result;
}
