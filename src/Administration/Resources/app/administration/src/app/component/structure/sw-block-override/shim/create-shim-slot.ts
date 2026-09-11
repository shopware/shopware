/**
 * @sw-package framework
 * @private
 */
import { compile, withCtx, type ComponentInternalInstance, type Slot, type VNode } from 'vue';
import { isGloballyAllowed } from '@vue/shared';
import { getDataScopeOwner } from 'src/app/adapter/composition-extension-system/data-scope-helper';
import type { BlockEntry } from 'src/core/factory/transform-legacy-block-conditionals';
import useLegacyConditionContext from './legacy-condition-context';

type DataScope = Record<string | symbol, unknown>;
type HostScope = DataScope & { $?: ComponentInternalInstance };

/** @private */
export type ShimSlot = Slot & { dispose: () => void };

const warnedBlocks = new Set<string>();

/** @private */
export function getShimOwner(scope: object | null): ComponentInternalInstance | null {
    if (!scope) return null;
    return getDataScopeOwner(scope) ?? (scope as HostScope).$ ?? null;
}

/**
 * Render with the host's Vue context, so refs, slots, events and local assets belong to the host.
 * Each block instance owns its render cache and scope pointer; updates preserve DOM identity.
 *
 * @private
 */
export function createShimSlot(entry: BlockEntry, blockName: string): ShimSlot {
    const warningKey = `${entry.componentName}:${blockName}`;
    if (!warnedBlocks.has(warningKey)) {
        warnedBlocks.add(warningKey);
        console.warn(
            `[Shopware Deprecation] Block "${blockName}" in component "${entry.componentName}" ` +
                `uses a legacy Twig override. Migrate to: <sw-block extends="${blockName}">...</sw-block>`,
        );
    }

    let source: DataScope = {};
    let owner: ComponentInternalInstance | null = null;
    let render: ((scope: object, cache: unknown[]) => VNode | null) | undefined;
    const cache: unknown[] = [];
    const reservedChains = new Set<string>();
    const { reserveLegacyConditionCases, clearLegacyConditionChain } = useLegacyConditionContext();
    const scope = createRenderScope(
        () => source,
        () => owner,
    );

    const slot: Slot = (dataScope) => {
        source = (dataScope ?? {}) as DataScope;
        owner = getShimOwner(source);
        if (entry.legacyConditionCases.length && !owner) {
            throw new Error(
                `[sw-block] Legacy Twig conditional override for block "${blockName}" in component "${entry.componentName}" ` +
                    `requires host data scope. Pass :data="$dataScope" to <sw-block name="${blockName}">.`,
            );
        }
        render ??= compile(entry.innerTemplate, {
            ...owner?.appContext?.config.compilerOptions,
            ...(owner?.type && typeof owner.type !== 'function' ? owner.type.compilerOptions : {}),
        }) as unknown as NonNullable<typeof render>;

        entry.legacyConditionCases.forEach((reservation) => {
            const chainKey = owner?.uid === undefined ? reservation.chainKey : `${owner.uid}:${reservation.chainKey}`;
            reservedChains.add(chainKey);
            reserveLegacyConditionCases(chainKey, reservation, true);
        });

        const renderContent = () => {
            const node = render!.call(scope, scope, cache);
            return node ? [node] : [];
        };
        // Some callers pass a plain data object. Vue context is only available for an actual host instance.
        const renderInHost = owner?.vnode ? (withCtx(renderContent, owner) as () => VNode[]) : renderContent;
        return renderInHost();
    };

    return Object.assign(slot, {
        dispose() {
            reservedChains.forEach(clearLegacyConditionChain);
            reservedChains.clear();
        },
    });
}

function createRenderScope(getSource: () => DataScope, getOwner: () => ComponentInternalInstance | null): DataScope {
    return new Proxy({} as DataScope, {
        has(_target, key) {
            // Runtime-compiled templates use `with`. Leave generated locals and JavaScript globals outside it.
            return typeof key === 'string' && key[0] !== '_' && !isGloballyAllowed(key);
        },
        get(_target, key) {
            if (key === Symbol.unscopables) return undefined;
            const source = getSource();
            if (key === '$dataScope') return source;
            if (key in source) return source[key];
            const host = getOwner()?.proxy;
            return host ? (Reflect.get(host, key) as unknown) : undefined;
        },
        set(_target, key, value) {
            const source = getSource();
            const host = getOwner()?.proxy;
            return Reflect.set(key in source || !host ? source : host, key, value);
        },
    });
}

/** @private */
export function resetShimSlotState(): void {
    warnedBlocks.clear();
}
