/**
 * @sw-package framework
 * @group disabledCompat
 */

import { createShimSlot, resetShimSlotState } from 'src/app/component/structure/sw-block-override/shim/create-shim-slot';
import useLegacyConditionContext from 'src/app/component/structure/sw-block-override/shim/legacy-condition-context';
import type { BlockEntry } from 'src/core/factory/transform-legacy-block-conditionals';

function makeEntry(overrides: Partial<BlockEntry> = {}): BlockEntry {
    return {
        componentName: 'sw-product-detail',
        innerTemplate: '<div class="shim-content"></div>',
        legacyConditionCases: [],
        ...overrides,
    };
}

describe('app/component/structure/sw-block-override/shim/create-shim-slot.ts', () => {
    let consoleSpy: jest.SpyInstance;

    beforeEach(() => {
        consoleSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
    });

    afterEach(() => {
        consoleSpy.mockRestore();
        resetShimSlotState();
    });

    describe('createShimSlot', () => {
        it('returns a slot function', () => {
            const slot = createShimSlot(makeEntry(), 'test_block');

            expect(typeof slot).toBe('function');
        });

        it('emits a console.warn deprecation message when called for the first time with a block name', () => {
            createShimSlot(makeEntry(), 'warn_on_first_use');

            expect(consoleSpy).toHaveBeenCalledTimes(1);
        });

        it('includes the block name in the deprecation warning message', () => {
            createShimSlot(makeEntry(), 'warn_includes_block_name');

            expect(consoleSpy).toHaveBeenCalledWith(expect.stringContaining('warn_includes_block_name'));
        });

        it('includes the component name in the deprecation warning message', () => {
            createShimSlot(makeEntry({ componentName: 'sw-order-detail' }), 'warn_includes_comp_name');

            expect(consoleSpy).toHaveBeenCalledWith(expect.stringContaining('sw-order-detail'));
        });

        it('includes the native migration hint in the deprecation warning message', () => {
            createShimSlot(makeEntry(), 'warn_includes_hint');

            expect(consoleSpy).toHaveBeenCalledWith(expect.stringContaining('<sw-block extends='));
        });

        it('does not emit a second warning when called again with the same block name', () => {
            createShimSlot(makeEntry(), 'dedupe_block');
            createShimSlot(makeEntry(), 'dedupe_block');

            expect(consoleSpy).toHaveBeenCalledTimes(1);
        });

        it('emits separate warnings for two distinct block names', () => {
            createShimSlot(makeEntry(), 'distinct_block_a');
            createShimSlot(makeEntry(), 'distinct_block_b');

            expect(consoleSpy).toHaveBeenCalledTimes(2);
        });

        it('the returned slot function returns an array', () => {
            const slot = createShimSlot(makeEntry(), 'slot_returns_array');

            const result = slot(null);

            expect(Array.isArray(result)).toBe(true);
        });

        it('the returned slot function returns a non-empty array', () => {
            const slot = createShimSlot(makeEntry(), 'slot_non_empty');

            const result = slot(null);

            expect(result.length).toBeGreaterThan(0);
        });

        it('exposes allowlisted legacy block helpers while keeping other $ keys hidden', () => {
            const legacyElse = jest.fn(() => true);
            const slot = createShimSlot(makeEntry(), 'slot_allowlisted_helper');
            const [vnode] = slot({
                $swLegacyBlockElse: legacyElse,
                $store: {},
            });
            const shimComponent = vnode.type as { setup: () => Record<string, unknown> };
            const setupContext = shimComponent.setup();

            expect(setupContext).toHaveProperty('$swLegacyBlockElse');
            expect(setupContext).not.toHaveProperty('$store');
            expect((setupContext.$swLegacyBlockElse as () => boolean)()).toBe(true);
            expect(legacyElse).toHaveBeenCalledTimes(1);
        });

        it('reserves legacy condition cases from block entry metadata', () => {
            const { legacyIf, legacyConditionContext, clearLegacyConditionChain } = useLegacyConditionContext();
            const chainKey = '42:reservation_chain';
            const slot = createShimSlot(
                makeEntry({
                    legacyConditionCases: [
                        {
                            chainKey: 'reservation_chain',
                            caseStartIndex: 0,
                            caseCount: 2,
                        },
                    ],
                }),
                'reservation_block',
            );

            legacyIf(chainKey, false, {
                segmentCaseIndex: 0,
                renderOrderSegment: 'defaultSlot',
                isStartingCondition: true,
            });
            slot({
                $: {
                    uid: 42,
                },
            });

            expect(legacyConditionContext[chainKey]).toStrictEqual({
                defaultSlotCases: [
                    {
                        result: false,
                        isStartingCondition: true,
                    },
                ],
                shimExtensionCases: [
                    undefined,
                    undefined,
                ],
                nativeExtensionCases: [],
                keepShimResultsForNextReservation: false,
            });

            clearLegacyConditionChain(chainKey);
        });

        it('clears reserved legacy condition chains on unmount', () => {
            const { legacyIf, legacyConditionContext } = useLegacyConditionContext();
            const chainKey = '43:clear_chain';
            const slot = createShimSlot(
                makeEntry({
                    legacyConditionCases: [
                        {
                            chainKey: 'clear_chain',
                            caseStartIndex: 0,
                            caseCount: 1,
                        },
                    ],
                }),
                'clear_block',
            );

            legacyIf(chainKey, false, {
                segmentCaseIndex: 0,
                renderOrderSegment: 'defaultSlot',
                isStartingCondition: true,
            });
            const [vnode] = slot({
                $: {
                    uid: 43,
                },
            });
            const shimComponent = vnode.type as { beforeUnmount: () => void };

            expect(legacyConditionContext[chainKey]).toBeDefined();

            shimComponent.beforeUnmount();

            expect(legacyConditionContext[chainKey]).toBeUndefined();
        });
    });

    describe('VNode type stability (focus preservation)', () => {
        it('returns the same VNode type reference on every slot call', () => {
            // Vue uses VNode type object identity to decide whether to reuse or
            // destroy a component instance. A new object on each call would cause
            // unmount + remount on every reactive update, destroying input focus.
            const slot = createShimSlot(makeEntry(), 'stable_type_repeated');

            const [vnode1] = slot(null);
            const [vnode2] = slot(null);
            const [vnode3] = slot(null);

            expect(vnode1.type).toBe(vnode2.type);
            expect(vnode2.type).toBe(vnode3.type);
        });

        it('returns the same VNode type reference when called with different dataScope values', () => {
            // The dataScope changes on every reactive update; the component type
            // must remain stable regardless so Vue can update in-place.
            const slot = createShimSlot(makeEntry(), 'stable_type_scope_change');

            const [vnode1] = slot({ label: 'a' });
            const [vnode2] = slot({ label: 'ab' });
            const [vnode3] = slot({ label: 'abc' });

            expect(vnode1.type).toBe(vnode2.type);
            expect(vnode2.type).toBe(vnode3.type);
        });

        it('creates independent component types for each createShimSlot invocation', () => {
            // Each call to createShimSlot owns a separate shimComponent object so
            // that different shim slots do not share — and therefore conflict on —
            // the same component identity during VDOM diffing.
            const slot1 = createShimSlot(makeEntry({ innerTemplate: '<div class="a"></div>' }), 'distinct_types_a');
            const slot2 = createShimSlot(makeEntry({ innerTemplate: '<div class="b"></div>' }), 'distinct_types_b');

            const [vnode1] = slot1(null);
            const [vnode2] = slot2(null);

            expect(vnode1.type).not.toBe(vnode2.type);
        });
    });

    describe('resetShimSlotState', () => {
        it('allows the deprecation warning to be emitted again for a previously warned block name', () => {
            createShimSlot(makeEntry(), 'reset_warn_block');
            expect(consoleSpy).toHaveBeenCalledTimes(1);

            resetShimSlotState();

            createShimSlot(makeEntry(), 'reset_warn_block');
            expect(consoleSpy).toHaveBeenCalledTimes(2);
        });

        it('clears the deduplication state for all block names, not just one', () => {
            createShimSlot(makeEntry(), 'reset_multi_a');
            createShimSlot(makeEntry(), 'reset_multi_b');
            expect(consoleSpy).toHaveBeenCalledTimes(2);

            resetShimSlotState();

            createShimSlot(makeEntry(), 'reset_multi_a');
            createShimSlot(makeEntry(), 'reset_multi_b');
            expect(consoleSpy).toHaveBeenCalledTimes(4);
        });
    });
});
