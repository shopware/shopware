/**
 * @sw-package framework
 */
import { createCommentVNode, h, nextTick, ref, watchEffect } from 'vue';
import {
    createBlockPass,
    getLegacyConditionChains,
    inferChainStart,
    legacyBlockHelpers,
    runBlockPass,
} from './legacy-condition-context';

const defaultCase = (segmentCaseIndex, isStartingCondition = false) => ({
    segmentCaseIndex,
    renderOrderSegment: 'defaultSlot',
    isStartingCondition,
});
const shimCase = (segmentCaseIndex, isStartingCondition = false) => ({
    segmentCaseIndex,
    renderOrderSegment: 'shimExtension',
    isStartingCondition,
});
const nativeCase = (segmentCaseIndex, isStartingCondition = false) => ({
    segmentCaseIndex,
    renderOrderSegment: 'nativeExtension',
    isStartingCondition,
});

function createHost() {
    const host = { $: {} };

    return {
        host,
        if: (key, expression, options) => legacyBlockHelpers.$swLegacyBlockIf.call(host, key, expression, options),
        elseIf: (key, expression, options) => legacyBlockHelpers.$swLegacyBlockElseIf.call(host, key, expression, options),
        else: (key, options) => legacyBlockHelpers.$swLegacyBlockElse.call(host, key, options),
    };
}

describe('app/component/structure/sw-block-override/shim/legacy-condition-context.ts', () => {
    it('evaluates if / else-if / else chains', () => {
        const chain = createHost();

        expect(chain.if('test', false, defaultCase(0, true))).toBe(false);
        expect(chain.elseIf('test', true, defaultCase(1))).toBe(true);
        expect(chain.else('test', defaultCase(2))).toBe(false);
    });

    it('renders the else case when no earlier case matched', () => {
        const chain = createHost();

        expect(chain.if('test', false, defaultCase(0, true))).toBe(false);
        expect(chain.elseIf('test', false, defaultCase(1))).toBe(false);
        expect(chain.else('test', defaultCase(2))).toBe(true);
    });

    it('does not render orphaned else cases', () => {
        const chain = createHost();

        expect(chain.elseIf('test', true, defaultCase(0))).toBe(false);
        expect(chain.else('test', defaultCase(1))).toBe(false);
    });

    it('evaluates shim cases after default cases and before native cases', () => {
        const chain = createHost();

        chain.if('test', false, defaultCase(0, true));

        expect(chain.elseIf('test', true, shimCase(0))).toBe(true);
        expect(chain.else('test', nativeCase(0))).toBe(false);
    });

    it('starts a new chain at a starting case', () => {
        const chain = createHost();

        chain.if('test', true, defaultCase(0, true));
        chain.if('test', false, shimCase(0, true));

        expect(chain.else('test', nativeCase(0))).toBe(true);
    });

    it('keeps the chains of different hosts apart', () => {
        const first = createHost();
        const second = createHost();

        first.if('test', false, defaultCase(0, true));

        expect(second.else('test', defaultCase(1))).toBe(false);
        expect(first.else('test', defaultCase(1))).toBe(true);
    });

    it('stores the chains on the host instance instead of a module-level record', () => {
        const chain = createHost();

        chain.if('test', true, defaultCase(0, true));

        expect([...getLegacyConditionChains(chain.host.$).keys()]).toEqual(['test']);
        expect(getLegacyConditionChains({})).toBeUndefined();
    });

    it('drops the cases a block no longer renders', () => {
        const chain = createHost();
        const pass = createBlockPass();

        runBlockPass(pass, () => {
            chain.if('test', false, defaultCase(0, true));
            chain.elseIf('test', true, nativeCase(0));
        });
        runBlockPass(pass, () => {
            chain.if('test', false, defaultCase(0, true));

            expect(chain.else('test', nativeCase(1))).toBe(true);
        });
    });

    it('re-renders a block that reads a case another block wrote, once that case changes', async () => {
        const chain = createHost();
        const writer = createBlockPass();
        const reader = createBlockPass();
        let condition = false;
        const results = [];

        const renderWriter = () => runBlockPass(writer, () => chain.if('test', condition, defaultCase(0, true)));
        renderWriter();
        watchEffect(() => {
            results.push(runBlockPass(reader, () => chain.else('test', defaultCase(1))));
        });

        condition = true;
        renderWriter();
        await nextTick();

        expect(results).toEqual([true, false]);
    });

    it('does not re-render a block for the cases it wrote itself', async () => {
        const chain = createHost();
        const pass = createBlockPass();
        const condition = ref(false);
        let renders = 0;

        watchEffect(() => {
            renders += 1;
            runBlockPass(pass, () => {
                chain.if('test', condition.value, defaultCase(0, true));
                chain.else('test', nativeCase(0));
            });
        });
        condition.value = true;
        await nextTick();
        await nextTick();

        expect(renders).toBe(2);
    });

    describe('inferChainStart', () => {
        it('starts an unmatched chain from default content that ends with a v-if placeholder', () => {
            const chain = createHost();

            inferChainStart(chain.host.$, 'test', [h('div'), createCommentVNode('v-if')]);

            expect(chain.else('test', shimCase(0))).toBe(true);
        });

        it('starts a matched chain from default content that ends with an element', () => {
            const chain = createHost();

            inferChainStart(chain.host.$, 'test', [createCommentVNode('author comment'), h('div')]);

            expect(chain.else('test', shimCase(0))).toBe(false);
        });

        it('keeps a chain started by rewritten default content', () => {
            const chain = createHost();

            chain.if('test', true, defaultCase(0, true));
            inferChainStart(chain.host.$, 'test', [createCommentVNode('v-if')]);

            expect(chain.else('test', shimCase(0))).toBe(false);
        });
    });
});
