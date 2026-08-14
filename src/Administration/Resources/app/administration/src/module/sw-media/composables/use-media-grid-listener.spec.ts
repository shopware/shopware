/**
 * @sw-package discovery
 */
import { reactive } from 'vue';
import { useMediaGridListener } from './use-media-grid-listener';
import type { MediaGridItem } from './use-media-grid-listener';

/**
 * The selection compares items by identity, and the composable holds them in a
 * deeply reactive ref. Real grid items already come from a reactive entity
 * collection, so the test items have to be reactive too — otherwise every read
 * would hand back a fresh proxy of a plain object.
 */
function createItem(id: string, entityName = 'media'): MediaGridItem {
    return reactive({ id, getEntityName: () => entityName });
}

function createComposable(items: MediaGridItem[]) {
    const onFolderChange = jest.fn();

    return {
        onFolderChange,
        composable: useMediaGridListener({ selectableItems: () => items, onFolderChange }),
    };
}

function click(item: MediaGridItem, modifiers: Partial<MouseEvent> = {}) {
    return { originalDomEvent: modifiers as MouseEvent, item };
}

describe('src/module/sw-media/composables/use-media-grid-listener', () => {
    it('selects a single item on a plain click', () => {
        const items = [
            createItem('a'),
            createItem('b'),
        ];
        const { composable } = createComposable(items);

        composable.handleMediaItemClicked(click(items[0]));

        expect(composable.selectedItems.value).toEqual([items[0]]);
        expect(composable.isListSelect.value).toBe(false);
        expect(composable.showItemSelected(items[0])).toBe(true);
        expect(composable.showItemSelected(items[1])).toBe(false);
    });

    it('reports the folder change instead of emitting it itself', () => {
        const folder = createItem('folder-1', 'media_folder');
        const { onFolderChange, composable } = createComposable([folder]);

        composable.showDetails(folder);

        expect(onFolderChange).toHaveBeenCalledWith('folder-1');
    });

    it('adds and removes items with a ctrl click', () => {
        const items = [
            createItem('a'),
            createItem('b'),
        ];
        const { composable } = createComposable(items);

        composable.handleMediaItemClicked(click(items[0], { ctrlKey: true }));
        composable.handleMediaItemClicked(click(items[1], { ctrlKey: true }));
        expect(composable.selectedItems.value).toEqual(items);

        composable.handleMediaItemClicked(click(items[1], { ctrlKey: true }));
        expect(composable.selectedItems.value).toEqual([items[0]]);
    });

    it('selects the range between the start item and the shift-clicked item', () => {
        const items = [
            createItem('a'),
            createItem('b'),
            createItem('c'),
            createItem('d'),
        ];
        const { composable } = createComposable(items);

        composable.handleMediaItemClicked(click(items[1]));
        composable.handleMediaItemClicked(click(items[3], { shiftKey: true }));

        expect(composable.selectedItems.value).toEqual(items.slice(1, 4));
    });

    it('takes the selectable items from the caller, not from its own state', () => {
        const items = [
            createItem('a'),
            createItem('b'),
            createItem('c'),
        ];
        const selectable: MediaGridItem[] = [];
        const composable = useMediaGridListener({
            selectableItems: () => selectable,
            onFolderChange: jest.fn(),
        });

        composable.handleMediaItemClicked(click(items[0]));
        selectable.push(...items);
        composable.handleMediaItemClicked(click(items[2], { shiftKey: true }));

        expect(composable.selectedItems.value).toEqual(items);
    });

    it('clears the selection and the list selection start', () => {
        const items = [
            createItem('a'),
            createItem('b'),
        ];
        const { composable } = createComposable(items);

        composable.handleMediaGridItemSelected(click(items[0]));
        composable.handleMediaGridItemSelected(click(items[1]));
        expect(composable.isListSelect.value).toBe(true);

        composable.clearSelection();

        expect(composable.selectedItems.value).toEqual([]);
        expect(composable.listSelectionStartItem.value).toBeNull();
    });

    it('unselects an item through the selection handler map', () => {
        const items = [
            createItem('a'),
            createItem('b'),
        ];
        const { composable } = createComposable(items);

        composable.handleMediaGridItemSelected(click(items[0]));
        composable.handleMediaGridItemSelected(click(items[1]));
        composable.mediaItemSelectionHandler.value['media-item-selection-remove'](click(items[0]));

        expect(composable.selectedItems.value).toEqual([items[1]]);
    });
});
