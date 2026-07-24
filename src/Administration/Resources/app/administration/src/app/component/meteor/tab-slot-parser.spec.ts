import { Fragment, type VNode } from 'vue';
import type { TabItem } from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';
import { isFragment, getTextFromSlotItem, triggerTabItemClick, getTabItemsFromSlotContent } from './tab-slot-parser';

/**
 * @sw-package framework
 */

function vnode(partial: Record<string, unknown>): VNode {
    return partial as unknown as VNode;
}

describe('src/app/component/meteor/tab-slot-parser', () => {
    describe('isFragment', () => {
        it('detects the Fragment type', () => {
            expect(isFragment(vnode({ type: Fragment }))).toBe(true);
        });

        it('detects the v-fgt fragment symbol', () => {
            expect(isFragment(vnode({ type: Symbol('v-fgt') }))).toBe(true);
        });

        it('returns false for a regular element', () => {
            expect(isFragment(vnode({ type: 'div' }))).toBe(false);
        });
    });

    describe('getTextFromSlotItem', () => {
        it('returns string children directly', () => {
            expect(getTextFromSlotItem(vnode({ children: 'Tab label' }))).toBe('Tab label');
        });

        it('joins nested array children recursively', () => {
            const nested = vnode({
                children: [
                    vnode({ children: 'Hello ' }),
                    vnode({ children: [vnode({ children: 'World' })] }),
                ],
            });

            expect(getTextFromSlotItem(nested)).toBe('Hello World');
        });

        it('returns an empty string for non-text children', () => {
            expect(getTextFromSlotItem(vnode({ children: null }))).toBe('');
        });
    });

    describe('triggerTabItemClick', () => {
        it('calls a single function handler', () => {
            const handler = jest.fn();

            triggerTabItemClick(handler);

            expect(handler).toHaveBeenCalledTimes(1);
        });

        it('calls every handler in an array', () => {
            const first = jest.fn();
            const second = jest.fn();

            triggerTabItemClick([
                first,
                second,
            ]);

            expect(first).toHaveBeenCalledTimes(1);
            expect(second).toHaveBeenCalledTimes(1);
        });

        it('is a no-op for undefined', () => {
            expect(() => triggerTabItemClick(undefined)).not.toThrow();
        });
    });

    describe('getTabItemsFromSlotContent', () => {
        const handlers = {
            isTabItem: (item: VNode) => item.type === 'sw-tabs-item',
            createTabItem: (item: VNode): TabItem => ({
                label: (item.props?.name as string) ?? '',
                name: (item.props?.name as string) ?? '',
            }),
        };

        it('maps tab items and skips non-tab nodes', () => {
            const slotContent = [
                vnode({ type: 'sw-tabs-item', props: { name: 'general' } }),
                vnode({ type: 'div', props: {} }),
                vnode({ type: 'sw-tabs-item', props: { name: 'advanced' } }),
            ];

            const result = getTabItemsFromSlotContent(slotContent, handlers);

            expect(result).toEqual([
                { label: 'general', name: 'general' },
                { label: 'advanced', name: 'advanced' },
            ]);
        });

        it('flattens tab items nested inside fragments', () => {
            const slotContent = [
                vnode({
                    type: Fragment,
                    children: [
                        vnode({ type: 'sw-tabs-item', props: { name: 'general' } }),
                        vnode({ type: 'sw-tabs-item', props: { name: 'advanced' } }),
                    ],
                }),
            ];

            const result = getTabItemsFromSlotContent(slotContent, handlers);

            expect(result).toEqual([
                { label: 'general', name: 'general' },
                { label: 'advanced', name: 'advanced' },
            ]);
        });

        it('returns an empty array when no tab items are present', () => {
            const slotContent = [vnode({ type: 'div', props: {} })];

            expect(getTabItemsFromSlotContent(slotContent, handlers)).toEqual([]);
        });
    });
});
