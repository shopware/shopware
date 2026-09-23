/**
 * @sw-package framework
 * @private
 *
 * Renders a legacy Twig block override as a layer of a native `<sw-block name>`. The reconstructed template is
 * compiled once and rendered in the context of the host component, as if it were still part of the host
 * template: identifiers, writes, `v-model`, `ref`, `$emit`, `$slots` and `$t` all resolve on the host.
 */
import { compile, withCtx, type ComponentInternalInstance, type VNodeArrayChildren } from 'vue';
import type { BlockEntry } from 'src/core/factory/transform-legacy-block-conditionals';
import type { BlockLayer } from '../../../../composables/use-block-context';
import { inferChainStart } from './legacy-condition-context';

type RenderContext = Record<string | symbol, unknown>;
type CompilerOptions = ComponentInternalInstance['appContext']['config']['compilerOptions'];
type ShimRender = (this: RenderContext, context: RenderContext, cache: unknown[]) => VNodeArrayChildren[number];

// Vue's `isGloballyAllowed`: identifiers a runtime-compiled template reads from the global scope.
const GLOBALS = new Set(
    'Infinity,undefined,NaN,isFinite,isNaN,parseFloat,parseInt,decodeURI,decodeURIComponent,encodeURI,encodeURIComponent,Math,Number,Date,Array,Object,Boolean,String,RegExp,Map,Set,JSON,Intl,BigInt,console,Error,Symbol'.split(
        ',',
    ),
);

const warnedBlocks = new Set<string>();

function warnOnce(blockName: string, componentName: string): void {
    if (warnedBlocks.has(blockName)) {
        return;
    }

    warnedBlocks.add(blockName);
    console.warn(
        `[Shopware Deprecation] Block "${blockName}" in component "${componentName}" ` +
            `uses a legacy Twig override. ` +
            `Migrate to: <sw-block extends="${blockName}">...</sw-block>`,
    );
}

/**
 * The `_ctx` of the compiled render function. Setup state of a native setup host is read and written through
 * its data scope, everything else through the host proxy. `has` mirrors Vue's proxy for runtime-compiled
 * templates, which run inside `with (_ctx)`.
 */
function createRenderContext(host: ComponentInternalInstance, scope: unknown): RenderContext {
    const proxy = host.proxy as unknown as RenderContext;
    const state = scope && typeof scope === 'object' && scope !== proxy ? (scope as RenderContext) : null;
    const owner = (key: string | symbol) =>
        state && typeof key === 'string' && key[0] !== '$' && key in state ? state : proxy;

    return new Proxy({} as RenderContext, {
        get: (_target, key) => (key === Symbol.unscopables ? undefined : owner(key)[key]),
        set: (_target, key, value) => {
            owner(key)[key] = value;

            return true;
        },
        has: (_target, key) => typeof key === 'string' && key[0] !== '_' && !GLOBALS.has(key),
    });
}

/**
 * @private
 */
export function createTwigShimLayer(
    blockName: string,
    getEntry: () => BlockEntry,
    options: Pick<BlockLayer, 'componentName' | 'scoped' | 'priority' | 'sequence'>,
): BlockLayer {
    let compiled: { template: string; options: CompilerOptions; render: ShimRender } | undefined;

    return {
        ...options,
        legacy: true,
        render(scope, { host, cache, index, previous }): VNodeArrayChildren {
            const entry = getEntry();

            warnOnce(blockName, entry.componentName);

            if (index === 1) {
                entry.legacyConditionCases
                    .filter(({ startsChain }) => !startsChain)
                    .forEach(({ chainKey }) => inferChainStart(host, chainKey, previous));
            }

            const compilerOptions = host.appContext.config.compilerOptions;

            if (compiled?.template !== entry.innerTemplate || compiled.options !== compilerOptions) {
                compiled = {
                    template: entry.innerTemplate,
                    options: compilerOptions,
                    render: compile(entry.innerTemplate, compilerOptions) as unknown as ShimRender,
                };
            }

            const { render } = compiled;
            const context = createRenderContext(host, scope);
            // `withCtx` makes the host the current rendering instance, which owns template refs, scope ids
            // and component resolution.
            const renderInHost = withCtx(
                () => render.call(context, context, cache),
                host,
            ) as () => VNodeArrayChildren[number];

            return [renderInHost()];
        },
    };
}

/**
 * @private
 */
export function resetShimSlotState(): void {
    warnedBlocks.clear();
}
