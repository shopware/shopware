import { Fragment, type VNode } from 'vue';
import type { TabItem } from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';

/**
 * @sw-package framework
 *
 * Shared helpers that translate slotted `<sw-tabs-item>` VNodes into `mt-tabs`
 * `TabItem[]` metadata. Used by both `sw-meteor-card` and `sw-meteor-page`, which
 * only differ in how a single tab item is built (routed vs. local state), so those
 * two steps are injected via `handlers`.
 */

/**
 * @private
 */
export type TabItemClickHandler = (() => void) | Array<() => void> | undefined;

/**
 * @private
 */
export type TabItemHandlers = {
    isTabItem: (item: VNode) => boolean;
    createTabItem: (item: VNode) => TabItem;
};

/**
 * @private
 */
export function isFragment(item: VNode): boolean {
    return item.type === Fragment || (typeof item.type === 'symbol' && item.type.toString() === 'Symbol(v-fgt)');
}

/**
 * @private
 */
export function getTextFromSlotItem(slotItem: VNode): string {
    if (typeof slotItem.children === 'string') {
        return slotItem.children;
    }

    if (Array.isArray(slotItem.children)) {
        return (slotItem.children as VNode[]).map((child) => getTextFromSlotItem(child)).join('');
    }

    return '';
}

/**
 * @private
 */
export function triggerTabItemClick(clickHandler: TabItemClickHandler): void {
    if (Array.isArray(clickHandler)) {
        clickHandler.forEach((handler) => {
            handler();
        });

        return;
    }

    if (typeof clickHandler === 'function') {
        clickHandler();
    }
}

/**
 * @private
 */
export function getTabItemsFromSlotContent(slotContent: VNode[], handlers: TabItemHandlers): TabItem[] {
    return slotContent.reduce<TabItem[]>((items, item) => {
        if (isFragment(item)) {
            const children = Array.isArray(item.children) ? (item.children as VNode[]) : [];

            return [
                ...items,
                ...getTabItemsFromSlotContent(children, handlers),
            ];
        }

        if (!handlers.isTabItem(item)) {
            return items;
        }

        return [
            ...items,
            handlers.createTabItem(item),
        ];
    }, []);
}
