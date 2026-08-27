/**
 * @sw-package discovery
 */
import useMediaGridListener, { type MediaGridItem, type MediaGridItemEvent } from './use-media-grid-listener';

function item(id: string, entityName = 'media'): MediaGridItem {
    return { id, getEntityName: () => entityName };
}

function domEvent(modifier?: 'shift' | 'ctrl' | 'meta'): MediaGridItemEvent['originalDomEvent'] {
    return {
        shiftKey: modifier === 'shift',
        ctrlKey: modifier === 'ctrl',
        metaKey: modifier === 'meta',
    };
}

describe('src/app/composables/use-media-grid-listener', () => {
    const items = [
        item('a'),
        item('b'),
        item('c'),
        item('d'),
    ];
    const onFolderChange = jest.fn();

    function listener(): ReturnType<typeof useMediaGridListener> {
        return useMediaGridListener({ selectableItems: () => items, onFolderChange });
    }

    beforeEach(() => {
        onFolderChange.mockClear();
    });

    it('replaces the selection on a plain click', () => {
        const { handleMediaItemClicked, selectedItems, isListSelect } = listener();

        handleMediaItemClicked({ originalDomEvent: domEvent(), item: items[0] });
        handleMediaItemClicked({ originalDomEvent: domEvent(), item: items[1] });

        expect(selectedItems.value).toEqual([items[1]]);
        expect(isListSelect.value).toBe(false);
    });

    it('reports a folder click instead of selecting it', () => {
        const { handleMediaItemClicked } = listener();
        const folder = item('folder-1', 'media_folder');

        handleMediaItemClicked({ originalDomEvent: domEvent(), item: folder });

        expect(onFolderChange).toHaveBeenCalledWith('folder-1');
    });

    it.each([
        'ctrl',
        'meta',
    ] as const)('toggles an item with the %s modifier', (modifier) => {
        const { handleMediaItemClicked, selectedItems, isItemSelected } = listener();

        handleMediaItemClicked({ originalDomEvent: domEvent(), item: items[0] });
        handleMediaItemClicked({ originalDomEvent: domEvent(modifier), item: items[1] });

        expect(selectedItems.value).toEqual([
            items[0],
            items[1],
        ]);

        handleMediaItemClicked({ originalDomEvent: domEvent(modifier), item: items[1] });

        expect(isItemSelected(items[1])).toBe(false);
    });

    // The range spans the host's selectableItems, which is the only reason the composable needs it.
    it('selects the range between the anchor and the shift-clicked item', () => {
        const { handleMediaItemClicked, selectedItems, listSelectionStartItem } = listener();

        handleMediaItemClicked({ originalDomEvent: domEvent(), item: items[3] });
        handleMediaItemClicked({ originalDomEvent: domEvent('shift'), item: items[1] });

        expect(selectedItems.value).toEqual([
            items[1],
            items[2],
            items[3],
        ]);
        expect(listSelectionStartItem.value).toBe(items[1]);
    });

    it('adds and removes items through the grid selection events', () => {
        const { handleMediaGridItemSelected, handleMediaGridItemUnselected, selectedItems } = listener();

        handleMediaGridItemSelected({ originalDomEvent: domEvent(), item: items[0] });
        handleMediaGridItemSelected({ originalDomEvent: domEvent(), item: items[1] });

        expect(selectedItems.value).toEqual([
            items[0],
            items[1],
        ]);

        handleMediaGridItemUnselected({ item: items[0] });

        expect(selectedItems.value).toEqual([items[1]]);
    });

    it('moves the anchor along when the anchor itself is unselected', () => {
        const { handleMediaGridItemSelected, handleMediaGridItemUnselected, listSelectionStartItem } = listener();

        handleMediaGridItemSelected({ originalDomEvent: domEvent(), item: items[0] });
        handleMediaGridItemSelected({ originalDomEvent: domEvent(), item: items[1] });

        expect(listSelectionStartItem.value).toBe(items[0]);

        handleMediaGridItemUnselected({ item: items[0] });

        expect(listSelectionStartItem.value).toBe(items[1]);
    });

    it('clears the selection and the anchor', () => {
        const { handleMediaItemClicked, clearSelection, selectedItems, listSelectionStartItem } = listener();

        handleMediaItemClicked({ originalDomEvent: domEvent(), item: items[0] });
        clearSelection();

        expect(selectedItems.value).toEqual([]);
        expect(listSelectionStartItem.value).toBeNull();
    });

    it('selects a single item for the detail view', () => {
        const { showDetails, showItemSelected } = listener();

        showDetails(items[2]);

        expect(showItemSelected(items[2])).toBe(true);
    });

    it('maps the media item events onto its handlers', () => {
        const { mediaItemSelectionHandler } = listener();

        expect(Object.keys(mediaItemSelectionHandler.value)).toEqual([
            'media-item-click',
            'media-item-selection-add',
            'media-item-selection-remove',
            'media-item-play',
        ]);
    });
});
