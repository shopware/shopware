/**
 * @sw-package framework
 */
import { getCurrentExtensionId } from 'src/core/extension-api';

// Import store to register it
import './extension-order-tracker.store';

// Mock the extension API
jest.mock('src/core/extension-api', () => ({
    getCurrentExtensionId: jest.fn(),
}));

const mockedGetCurrentExtensionId = getCurrentExtensionId as jest.MockedFunction<typeof getCurrentExtensionId>;

describe('extensionOrderTracker.store', () => {
    let store: ReturnType<typeof Shopware.Store.get<'extensionOrderTracker'>>;

    beforeEach(() => {
        // Reset mocks
        mockedGetCurrentExtensionId.mockReset();
        mockedGetCurrentExtensionId.mockReturnValue('default-extension');

        // Get fresh store instance
        store = Shopware.Store.get('extensionOrderTracker');
    });

    describe('registerPush', () => {
        it('should return index 0 for first push', () => {
            const arr: string[] = [];
            const index = store.registerPush(arr);
            expect(index).toBe(0);
        });

        it('should return sequential indices for same extension', () => {
            const arr: string[] = [];

            expect(store.registerPush(arr)).toBe(0);
            expect(store.registerPush(arr)).toBe(1);
            expect(store.registerPush(arr)).toBe(2);
        });

        it('should group indices by extension', () => {
            const arr: string[] = [];

            // Extension A pushes
            mockedGetCurrentExtensionId.mockReturnValue('ext-a');
            expect(store.registerPush(arr)).toBe(0);

            // Extension B pushes
            mockedGetCurrentExtensionId.mockReturnValue('ext-b');
            expect(store.registerPush(arr)).toBe(1);

            // Extension A pushes again - should be grouped with previous A entries
            mockedGetCurrentExtensionId.mockReturnValue('ext-a');
            expect(store.registerPush(arr)).toBe(1); // After ext-a's first entry
        });

        it('should use null as group when no extension context', () => {
            mockedGetCurrentExtensionId.mockReturnValue(null);
            const arr: string[] = [];

            // Should work without throwing, using null as the group
            expect(store.registerPush(arr)).toBe(0);
            expect(store.registerPush(arr)).toBe(1);
        });

        it('should keep null group separate from extension groups', () => {
            const arr: string[] = [];

            // Shopware core context (null)
            mockedGetCurrentExtensionId.mockReturnValue(null);
            expect(store.registerPush(arr)).toBe(0);

            // Extension context
            mockedGetCurrentExtensionId.mockReturnValue('my-extension');
            expect(store.registerPush(arr)).toBe(1);

            // Back to shopware core - should be grouped with first null entry
            mockedGetCurrentExtensionId.mockReturnValue(null);
            expect(store.registerPush(arr)).toBe(1); // After the first null entry
        });
    });

    describe('registerFlush', () => {
        it('should return empty range for untracked array', () => {
            const arr: string[] = [];
            const range = store.registerFlush(arr);

            expect(range).toEqual({ start: 0, deleteCount: 0 });
        });

        it('should return correct range for tracked extension', () => {
            const arr: string[] = [];

            // Push 3 items from ext-a
            mockedGetCurrentExtensionId.mockReturnValue('ext-a');
            store.registerPush(arr);
            store.registerPush(arr);
            store.registerPush(arr);

            // Push 2 items from ext-b
            mockedGetCurrentExtensionId.mockReturnValue('ext-b');
            store.registerPush(arr);
            store.registerPush(arr);

            // Flush ext-a
            mockedGetCurrentExtensionId.mockReturnValue('ext-a');
            const range = store.registerFlush(arr);

            expect(range).toEqual({ start: 0, deleteCount: 3 });
        });

        it('should allow explicit extensionId parameter', () => {
            const arr: string[] = [];

            mockedGetCurrentExtensionId.mockReturnValue('ext-a');
            store.registerPush(arr);
            store.registerPush(arr);

            // Flush with explicit ID, not current context
            const range = store.registerFlush(arr, 'ext-a');

            expect(range).toEqual({ start: 0, deleteCount: 2 });
        });
    });

    describe('insert', () => {
        it('should insert at correct position', () => {
            const arr: string[] = [];

            mockedGetCurrentExtensionId.mockReturnValue('ext-a');
            store.insert(arr, 'a1');

            mockedGetCurrentExtensionId.mockReturnValue('ext-b');
            store.insert(arr, 'b1');

            mockedGetCurrentExtensionId.mockReturnValue('ext-a');
            store.insert(arr, 'a2');

            // ext-a entries grouped together, then ext-b
            expect(arr).toEqual(['a1', 'a2', 'b1']);
        });

        it('should handle multiple values', () => {
            const arr: string[] = [];

            mockedGetCurrentExtensionId.mockReturnValue('ext-a');
            store.insert(arr, 'a1', 'a2', 'a3');

            expect(arr).toEqual(['a1', 'a2', 'a3']);
        });

        it('should handle interleaved inserts from multiple extensions', () => {
            const arr: string[] = [];

            mockedGetCurrentExtensionId.mockReturnValue('ext-1');
            store.insert(arr, 'ext1-a');

            mockedGetCurrentExtensionId.mockReturnValue('ext-2');
            store.insert(arr, 'ext2-a');

            mockedGetCurrentExtensionId.mockReturnValue('ext-3');
            store.insert(arr, 'ext3-a');

            mockedGetCurrentExtensionId.mockReturnValue('ext-1');
            store.insert(arr, 'ext1-b');

            mockedGetCurrentExtensionId.mockReturnValue('ext-2');
            store.insert(arr, 'ext2-b');

            expect(arr).toEqual(['ext1-a', 'ext1-b', 'ext2-a', 'ext2-b', 'ext3-a']);
        });
    });

    describe('flush', () => {
        it('should remove entries for current extension', () => {
            const arr: string[] = [];

            mockedGetCurrentExtensionId.mockReturnValue('ext-a');
            store.insert(arr, 'a1', 'a2');

            mockedGetCurrentExtensionId.mockReturnValue('ext-b');
            store.insert(arr, 'b1');

            mockedGetCurrentExtensionId.mockReturnValue('ext-a');
            store.flush(arr);

            expect(arr).toEqual(['b1']);
        });

        it('should preserve slot order after flush', () => {
            const arr: string[] = [];

            mockedGetCurrentExtensionId.mockReturnValue('ext-a');
            store.insert(arr, 'a-first');

            mockedGetCurrentExtensionId.mockReturnValue('ext-b');
            store.insert(arr, 'b-entry');

            // Flush ext-a
            mockedGetCurrentExtensionId.mockReturnValue('ext-a');
            store.flush(arr);

            // Insert from ext-a again - should still come before ext-b
            store.insert(arr, 'a-after-flush');

            expect(arr).toEqual(['a-after-flush', 'b-entry']);
        });

        it('should allow explicit extensionId parameter', () => {
            const arr: string[] = [];

            mockedGetCurrentExtensionId.mockReturnValue('ext-a');
            store.insert(arr, 'a1');

            mockedGetCurrentExtensionId.mockReturnValue('ext-b');
            store.insert(arr, 'b1');

            // Flush ext-a using explicit parameter while in ext-b context
            store.flush(arr, 'ext-a');

            expect(arr).toEqual(['b1']);
        });
    });

    describe('flushMap', () => {
        it('should flush all arrays in a map', () => {
            const map: Record<string, string[]> = {
                pos1: [],
                pos2: [],
            };

            mockedGetCurrentExtensionId.mockReturnValue('ext-a');
            store.insert(map.pos1, 'a-in-pos1');
            store.insert(map.pos2, 'a-in-pos2');

            mockedGetCurrentExtensionId.mockReturnValue('ext-b');
            store.insert(map.pos1, 'b-in-pos1');

            // Flush ext-a from all positions
            store.flushMap(map, 'ext-a');

            expect(map.pos1).toEqual(['b-in-pos1']);
            expect(map.pos2).toEqual([]);
        });

        it('should use current extension when no explicit ID provided', () => {
            const map: Record<string, string[]> = {
                key1: [],
            };

            mockedGetCurrentExtensionId.mockReturnValue('ext-a');
            store.insert(map.key1, 'a1');

            mockedGetCurrentExtensionId.mockReturnValue('ext-b');
            store.insert(map.key1, 'b1');

            mockedGetCurrentExtensionId.mockReturnValue('ext-a');
            store.flushMap(map);

            expect(map.key1).toEqual(['b1']);
        });
    });

    describe('integration: tabs store pattern', () => {
        it('should work like tabItems pattern', () => {
            const tabItems: Record<string, Array<{ label: string }>> = {};
            const positionId = 'sw-product-detail';

            // Initialize array
            tabItems[positionId] = [];

            // Extension A adds a tab
            mockedGetCurrentExtensionId.mockReturnValue('extension-a');
            store.insert(tabItems[positionId], { label: 'Tab from A' });

            // Extension B adds a tab
            mockedGetCurrentExtensionId.mockReturnValue('extension-b');
            store.insert(tabItems[positionId], { label: 'Tab from B' });

            // Extension A adds another tab
            mockedGetCurrentExtensionId.mockReturnValue('extension-a');
            store.insert(tabItems[positionId], { label: 'Another tab from A' });

            // Should be grouped by extension
            expect(tabItems[positionId].map((t) => t.label)).toEqual([
                'Tab from A',
                'Another tab from A',
                'Tab from B',
            ]);

            // Flush extension A (e.g., on extension reload)
            store.flushMap(tabItems, 'extension-a');

            expect(tabItems[positionId].map((t) => t.label)).toEqual(['Tab from B']);

            // Extension A comes back and adds a tab - should be at position 0 again
            mockedGetCurrentExtensionId.mockReturnValue('extension-a');
            store.insert(tabItems[positionId], { label: 'A is back' });

            expect(tabItems[positionId].map((t) => t.label)).toEqual(['A is back', 'Tab from B']);
        });
    });
});
