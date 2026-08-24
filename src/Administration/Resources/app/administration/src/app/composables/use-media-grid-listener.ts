/**
 * @sw-package discovery
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES
 */
import { computed, shallowRef, type ComputedRef, type ShallowRef } from 'vue';

/** @private */
export type MediaGridItem = {
    getEntityName: () => string;
    [key: string]: unknown;
};

/** @private */
export type MediaGridItemEvent = {
    originalDomEvent: { shiftKey: boolean; ctrlKey: boolean; metaKey: boolean };
    item: MediaGridItem;
};

/**
 * The mixin left `selectableItems` as an empty computed for its host to override, and emitted the
 * folder change off the instance. Both arrive as options, because a composable can declare neither.
 *
 * @private
 */
export interface UseMediaGridListenerOptions {
    /** The items a range selection may span, in the order the grid renders them. */
    selectableItems: () => MediaGridItem[];
    onFolderChange: (folderId: string) => void;
}

/**
 * Composable alternative to the `media-grid-listener` mixin: turns the click and selection events of
 * `sw-media-media-item` into a selection, including ctrl/meta toggling and shift ranges. The mixin
 * stays in place for Options API components.
 *
 * The mixin's `_`-prefixed helpers stay private here. `showDetails` is the public equivalent of
 * `_singleSelect`; everything else was internal bookkeeping.
 *
 * Keep this and `src/module/sw-media/mixin/media-grid-listener.mixin.js` in sync — change both
 * together.
 *
 * @private
 */
export default function useMediaGridListener(options: UseMediaGridListenerOptions): {
    selectedItems: ShallowRef<MediaGridItem[]>;
    listSelectionStartItem: ShallowRef<MediaGridItem | null>;
    mediaItemSelectionHandler: ComputedRef<Record<string, (event: MediaGridItemEvent) => void>>;
    isListSelect: ComputedRef<boolean>;
    isItemSelected: (itemToCompare: MediaGridItem) => boolean;
    showItemSelected: (item: MediaGridItem) => boolean;
    clearSelection: () => void;
    navigateToFolder: (event: { item: MediaGridItem }) => void;
    showDetails: (gridItem: MediaGridItem) => void;
    handleMediaItemClicked: (event: MediaGridItemEvent) => void;
    handleMediaGridItemSelected: (event: MediaGridItemEvent) => void;
    handleMediaGridItemUnselected: (event: { item: MediaGridItem }) => void;
} {
    // Shallow on purpose: the selection is tracked by item identity, which a deep ref would break by
    // handing out a reactive proxy of an item the caller passed in raw.
    const selectedItems = shallowRef<MediaGridItem[]>([]);
    const listSelectionStartItem = shallowRef<MediaGridItem | null>(null);

    const isListSelect = computed(() => listSelectionStartItem.value !== null);

    function isItemSelected(itemToCompare: MediaGridItem): boolean {
        return selectedItems.value.findIndex((item) => item === itemToCompare) > -1;
    }

    function showItemSelected(item: MediaGridItem): boolean {
        return isItemSelected(item);
    }

    function clearSelection(): void {
        selectedItems.value = [];
        listSelectionStartItem.value = null;
    }

    function navigateToFolder({ item }: { item: MediaGridItem }): void {
        options.onFolderChange(item.id as string);
    }

    function singleSelect(item: MediaGridItem): void {
        if (item.getEntityName() === 'media_folder') {
            navigateToFolder({ item });
        }

        selectedItems.value = [item];
        listSelectionStartItem.value = null;
    }

    function startListSelect(item: MediaGridItem): void {
        selectedItems.value = [item];
        listSelectionStartItem.value = item;
    }

    function removeItemFromSelection(item: MediaGridItem): void {
        selectedItems.value = selectedItems.value.filter((currentSelected) => currentSelected !== item);

        if (listSelectionStartItem.value === item) {
            listSelectionStartItem.value = selectedItems.value[0] || null;
        }
    }

    function addItemToSelection(item: MediaGridItem): void {
        if (!isListSelect.value) {
            // A second item turns the existing single selection into the anchor of a range.
            if (selectedItems.value.length === 1) {
                startListSelect(selectedItems.value[0]);
                addItemToSelection(item);
                return;
            }

            startListSelect(item);
            return;
        }

        if (!isItemSelected(item)) {
            selectedItems.value = [
                ...selectedItems.value,
                item,
            ];
        }
    }

    function findSelectionIndices(first: MediaGridItem, second: MediaGridItem): { start: number; end: number } {
        const items = options.selectableItems();
        const firstIndex = items.findIndex((selectableItem) => first === selectableItem);
        const secondIndex = items.findIndex((selectableItem) => second === selectableItem);

        return {
            start: Math.min(firstIndex, secondIndex),
            end: Math.max(firstIndex, secondIndex),
        };
    }

    function handleShiftSelect(item: MediaGridItem): void {
        if (!isListSelect.value) {
            if (selectedItems.value.length === 1) {
                startListSelect(selectedItems.value[0]);
                handleShiftSelect(item);
                return;
            }

            startListSelect(item);
            return;
        }

        if (item === listSelectionStartItem.value) {
            startListSelect(item);
            return;
        }

        const indices = findSelectionIndices(listSelectionStartItem.value as MediaGridItem, item);

        selectedItems.value = options.selectableItems().slice(indices.start, indices.end + 1);
        listSelectionStartItem.value = options.selectableItems()[indices.start];
    }

    function handleSelection(item: MediaGridItem): void {
        if (isItemSelected(item)) {
            removeItemFromSelection(item);
            return;
        }

        addItemToSelection(item);
    }

    function showDetails(gridItem: MediaGridItem): void {
        singleSelect(gridItem);
    }

    function handleMediaItemClicked({ originalDomEvent, item }: MediaGridItemEvent): void {
        if (originalDomEvent.shiftKey) {
            handleShiftSelect(item);
            return;
        }

        if (isListSelect.value || originalDomEvent.ctrlKey || originalDomEvent.metaKey) {
            handleSelection(item);
            return;
        }

        singleSelect(item);
    }

    function handleMediaGridItemSelected({ originalDomEvent, item }: MediaGridItemEvent): void {
        if (originalDomEvent.shiftKey) {
            handleShiftSelect(item);
            return;
        }

        addItemToSelection(item);
    }

    function handleMediaGridItemUnselected({ item }: { item: MediaGridItem }): void {
        removeItemFromSelection(item);
    }

    const mediaItemSelectionHandler = computed(() => ({
        'media-item-click': handleMediaItemClicked,
        'media-item-selection-add': handleMediaGridItemSelected,
        'media-item-selection-remove': handleMediaGridItemUnselected,
        'media-item-play': handleMediaItemClicked,
    }));

    return {
        selectedItems,
        listSelectionStartItem,
        mediaItemSelectionHandler,
        isListSelect,
        isItemSelected,
        showItemSelected,
        clearSelection,
        navigateToFolder,
        showDetails,
        handleMediaItemClicked,
        handleMediaGridItemSelected,
        handleMediaGridItemUnselected,
    };
}
