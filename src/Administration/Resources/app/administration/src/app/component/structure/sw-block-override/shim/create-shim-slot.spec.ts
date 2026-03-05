/**
 * @sw-package framework
 * @group disabledCompat
 */

import { createShimSlot, resetShimSlotState } from 'src/app/component/structure/sw-block-override/shim/create-shim-slot';
import type { BlockEntry } from 'src/core/factory/twig-block-index';

function makeEntry(overrides: Partial<BlockEntry> = {}): BlockEntry {
    return {
        componentName: 'sw-product-detail',
        innerTemplate: '<div class="shim-content"></div>',
        hasParent: false,
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

            expect(consoleSpy).toHaveBeenCalledWith(
                expect.stringContaining('warn_includes_block_name'),
            );
        });

        it('includes the component name in the deprecation warning message', () => {
            createShimSlot(makeEntry({ componentName: 'sw-order-detail' }), 'warn_includes_comp_name');

            expect(consoleSpy).toHaveBeenCalledWith(
                expect.stringContaining('sw-order-detail'),
            );
        });

        it('includes the native migration hint in the deprecation warning message', () => {
            createShimSlot(makeEntry(), 'warn_includes_hint');

            expect(consoleSpy).toHaveBeenCalledWith(
                expect.stringContaining('<sw-block extends='),
            );
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
