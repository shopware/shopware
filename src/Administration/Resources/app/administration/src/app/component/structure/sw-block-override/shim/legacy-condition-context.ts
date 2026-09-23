/**
 * @sw-package framework
 */
import { shallowRef, type ShallowRef, type VNodeArrayChildren } from 'vue';
import { endsWithUnmatchedCondition } from '../reduce-to-single-root';

/**
 * The layer a case sits in. Cases are evaluated in this order: default content, Twig shims, native extensions.
 *
 * @private
 */
export type LegacyConditionRenderOrderSegment = 'defaultSlot' | 'shimExtension' | 'nativeExtension';

/**
 * @private
 */
export type LegacyConditionCaseOptions = {
    segmentCaseIndex: number;
    renderOrderSegment: LegacyConditionRenderOrderSegment;
    isStartingCondition?: boolean;
};

type CaseResult = {
    result: boolean;
    starting: boolean;
    pass?: BlockPass;
    inferred?: boolean;
};

type LegacyConditionChain = Record<LegacyConditionRenderOrderSegment, Array<CaseResult | undefined>>;

type WrittenCase = { cases: Array<CaseResult | undefined>; index: number; entry: CaseResult };

/**
 * The cases one `<sw-block name>` wrote during its last render. Blocks that read those cases track `revision`.
 *
 * @private
 */
export type BlockPass = {
    revision: ShallowRef<number>;
    written: WrittenCase[];
};

type LegacyHelperThis = { $?: object };

const SEGMENTS: LegacyConditionRenderOrderSegment[] = [
    'defaultSlot',
    'shimExtension',
    'nativeExtension',
];

const chainsByHost = new WeakMap<object, Map<string, LegacyConditionChain>>();
let activePass: BlockPass | undefined;

function getChain(host: object, chainKey: string): LegacyConditionChain {
    let chains = chainsByHost.get(host);

    if (!chains) {
        chains = new Map();
        chainsByHost.set(host, chains);
    }

    let chain = chains.get(chainKey);

    if (!chain) {
        chain = { defaultSlot: [], shimExtension: [], nativeExtension: [] };
        chains.set(chainKey, chain);
    }

    return chain;
}

function record(chain: LegacyConditionChain, options: LegacyConditionCaseOptions, result: boolean, inferred = false) {
    const cases = chain[options.renderOrderSegment];
    const entry: CaseResult = { result, starting: options.isStartingCondition === true, pass: activePass, inferred };

    cases[options.segmentCaseIndex] = entry;
    activePass?.written.push({ cases, index: options.segmentCaseIndex, entry });

    return result;
}

/**
 * Whether a case before this one, back to the case that started the chain, matched. `undefined` when there is
 * no earlier case: an orphaned `v-else` renders nothing.
 */
function earlierCaseMatched(chain: LegacyConditionChain, options: LegacyConditionCaseOptions): boolean | undefined {
    const earlier: CaseResult[] = [];

    for (const segment of SEGMENTS) {
        const cases = chain[segment];
        const end = segment === options.renderOrderSegment ? options.segmentCaseIndex : cases.length;

        for (let index = 0; index < end; index += 1) {
            const entry = cases[index];

            if (entry?.starting) {
                earlier.length = 0;
            }

            if (entry) {
                earlier.push(entry);
            }
        }

        if (segment === options.renderOrderSegment) {
            break;
        }
    }

    earlier.forEach(({ pass }) => {
        if (pass && pass !== activePass) {
            // Re-renders this block when the block that wrote the case renders a different result.
            void pass.revision.value;
        }
    });

    return earlier.length === 0 ? undefined : earlier.some(({ result }) => result);
}

function legacyIf(host: object, chainKey: string, expression: unknown, options: LegacyConditionCaseOptions): boolean {
    return record(getChain(host, chainKey), options, Boolean(expression));
}

function legacyElseIf(host: object, chainKey: string, expression: unknown, options: LegacyConditionCaseOptions) {
    const chain = getChain(host, chainKey);
    const matched = earlierCaseMatched(chain, options);

    return matched !== undefined && record(chain, options, !matched && Boolean(expression));
}

function legacyElse(host: object, chainKey: string, options: LegacyConditionCaseOptions): boolean {
    const chain = getChain(host, chainKey);
    const matched = earlierCaseMatched(chain, options);

    return matched !== undefined && record(chain, options, !matched);
}

/**
 * @private
 */
export function createBlockPass(): BlockPass {
    return { revision: shallowRef(0), written: [] };
}

/**
 * Runs one render of a `<sw-block name>`. Its layers call the case helpers in order, so later cases see the
 * results of earlier ones from the same render. Cases the block no longer renders are dropped.
 *
 * @private
 */
export function runBlockPass<T>(pass: BlockPass, render: () => T): T {
    const previous = pass.written;
    const outerPass = activePass;

    previous.forEach(({ cases, index, entry }) => {
        if (cases[index] === entry) {
            cases[index] = undefined;
        }
    });
    pass.written = [];
    activePass = pass;

    try {
        return render();
    } finally {
        activePass = outerPass;

        const unchanged =
            previous.length === pass.written.length &&
            previous.every(
                ({ cases, index, entry }) =>
                    cases[index]?.result === entry.result && cases[index]?.starting === entry.starting,
            );

        if (!unchanged) {
            pass.revision.value += 1;
        }
    }
}

/**
 * Starts a chain from rendered default content whose conditionals were not rewritten, which is the case for
 * `.vue` hosts: a trailing `v-if` placeholder means no case of that chain matched.
 *
 * @private
 */
export function inferChainStart(host: object, chainKey: string, nodes: VNodeArrayChildren): void {
    const chain = getChain(host, chainKey);

    if (chain.defaultSlot.some((entry) => entry && !entry.inferred)) {
        return;
    }

    record(
        chain,
        { segmentCaseIndex: 0, renderOrderSegment: 'defaultSlot', isStartingCondition: true },
        !endsWithUnmatchedCondition(nodes),
        true,
    );
}

/**
 * Chain state of one host, for tests.
 *
 * @private
 */
export function getLegacyConditionChains(host: object): ReadonlyMap<string, LegacyConditionChain> | undefined {
    return chainsByHost.get(host);
}

/**
 * The `$swLegacyBlock*` global properties that rewritten templates call. `this` is the instance proxy or the
 * render context of a Twig shim; both resolve `$` to the host instance, which owns the chain state.
 *
 * @private
 */
export const legacyBlockHelpers = {
    $swLegacyBlockIf(this: LegacyHelperThis, chainKey: string, expression: unknown, options: LegacyConditionCaseOptions) {
        return legacyIf(this.$ ?? this, chainKey, expression, options);
    },
    $swLegacyBlockElseIf(
        this: LegacyHelperThis,
        chainKey: string,
        expression: unknown,
        options: LegacyConditionCaseOptions,
    ) {
        return legacyElseIf(this.$ ?? this, chainKey, expression, options);
    },
    $swLegacyBlockElse(this: LegacyHelperThis, chainKey: string, options: LegacyConditionCaseOptions) {
        return legacyElse(this.$ ?? this, chainKey, options);
    },
};
