/**
 * @sw-package framework
 *
 * Store for tracking extension entry ordering with proper grouping and flushing support.
 *
 * This store tracks which extension added which entries to arrays, allowing:
 * - Grouped ordering: entries from the same extension stay together
 * - Proper flushing: remove all entries from a specific extension
 * - Slot preservation: extensions keep their position after flush
 */

import { getCurrentExtensionId } from 'src/core/extension-api';

/**
 * Extension ID can be a string (extension origin), symbol, or null (shopware core context).
 */
type ExtensionId = string | symbol | null;

interface TrackingEntry {
    extensionId: ExtensionId;
    length: number;
}

/**
 * WeakMap to track arrays by reference.
 * Stored outside Pinia state to avoid reactivity issues with Proxy-of-Proxy.
 */
const trackingByTarget = new WeakMap<object, TrackingEntry[]>();

/**
 * Get or create tracking data for a target array.
 */
function getTracking(target: object): TrackingEntry[] {
    let tracking = trackingByTarget.get(target);
    if (!tracking) {
        tracking = [];
        trackingByTarget.set(target, tracking);
    }
    return tracking;
}

/**
 * Calculate insert index for the current extension and update tracking.
 * Returns the index where new items should be inserted.
 */
function calculateInsertIndex(tracking: TrackingEntry[], extensionId: ExtensionId): number {
    let index = 0;

    for (const entry of tracking) {
        if (entry.extensionId === extensionId) {
            // Found the extension - insert after its current entries
            index += entry.length;
            entry.length++;
            return index;
        }
        index += entry.length;
    }

    // Extension not found - add new tracking entry at the end
    tracking.push({ extensionId, length: 1 });
    return index;
}

/**
 * Calculate flush range for an extension and update tracking.
 * Returns { start, deleteCount } for splicing.
 */
function calculateFlushRange(
    tracking: TrackingEntry[],
    extensionId: ExtensionId,
): { start: number; deleteCount: number } {
    let startIndex = 0;

    for (const entry of tracking) {
        if (entry.extensionId === extensionId) {
            const deleteCount = entry.length;
            entry.length = 0; // Keep slot, reset length
            return { start: startIndex, deleteCount };
        }
        startIndex += entry.length;
    }

    // Extension not tracked - nothing to flush
    return { start: 0, deleteCount: 0 };
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
const extensionOrderTrackerStore = Shopware.Store.register({
    id: 'extensionOrderTracker',

    state: () => ({}),

    actions: {
        /**
         * Register a push operation and return the insert index.
         * Call this before inserting to get the correct position.
         *
         * @example
         * const index = store.registerPush(myArray);
         * myArray.splice(index, 0, newValue);
         */
        registerPush<T>(target: T[]): number {
            const extensionId = getCurrentExtensionId();
            const tracking = getTracking(target);
            return calculateInsertIndex(tracking, extensionId);
        },

        /**
         * Register a flush operation and return the range to splice.
         * Call this to get the range, then splice the array yourself.
         *
         * @example
         * const { start, deleteCount } = store.registerFlush(myArray);
         * myArray.splice(start, deleteCount);
         */
        registerFlush<T>(target: T[], extensionId?: ExtensionId): { start: number; deleteCount: number } {
            const effectiveExtensionId = extensionId !== undefined ? extensionId : getCurrentExtensionId();
            const tracking = getTracking(target);
            return calculateFlushRange(tracking, effectiveExtensionId);
        },

        /**
         * Insert values into the array at the correct position for the current extension.
         * If not in an extension context, uses `null` as the group (shopware core context).
         *
         * @example
         * store.insert(this.tabItems[positionId], { label: 'My Tab' });
         */
        insert<T>(target: T[], ...values: T[]): void {
            const extensionId = getCurrentExtensionId();
            const tracking = getTracking(target);

            for (const value of values) {
                const insertIndex = calculateInsertIndex(tracking, extensionId);
                target.splice(insertIndex, 0, value);
            }
        },

        /**
         * Flush (remove) all entries for the given extension from the array.
         * If no extensionId provided, uses current context (or `null` for shopware core).
         *
         * @example
         * store.flush(this.tabItems[positionId]);
         * store.flush(this.tabItems[positionId], 'my-extension-origin');
         */
        flush<T>(target: T[], extensionId?: ExtensionId): void {
            const effectiveExtensionId = extensionId !== undefined ? extensionId : getCurrentExtensionId();
            const tracking = getTracking(target);
            const { start, deleteCount } = calculateFlushRange(tracking, effectiveExtensionId);

            if (deleteCount > 0) {
                target.splice(start, deleteCount);
            }
        },

        /**
         * Flush all entries for an extension from all arrays in a map.
         * If no extensionId provided, uses current context (or `null` for shopware core).
         *
         * @example
         * store.flushMap(this.tabItems, extensionId);
         * store.flushMap(this.tabItems, null); // flush shopware core entries
         */
        flushMap<T>(map: Record<string, T[]>, extensionId?: ExtensionId): void {
            const effectiveExtensionId = extensionId !== undefined ? extensionId : getCurrentExtensionId();

            for (const key of Object.keys(map)) {
                const target = map[key];
                if (Array.isArray(target)) {
                    this.flush(target, effectiveExtensionId);
                }
            }
        },
    },
});

/**
 * @private
 */
export type ExtensionOrderTrackerStore = ReturnType<typeof extensionOrderTrackerStore>;

/**
 * @private
 */
export default extensionOrderTrackerStore;
