/**
 * @sw-package discovery
 */
import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';

/** @private */
export interface MediaGridItem {
    id: string;
    getEntityName: () => string;
}

/** @private */
export interface MediaGridItemEvent {
    originalDomEvent: MouseEvent;
    item: MediaGridItem;
}

/**
 * What the mixin took from the host component: the `media-folder-change` event it
 * emitted, and the `selectableItems` computed every consumer had to override.
 *
 * @private
 */
export interface UseMediaGridListenerOptions {
    selectableItems: () => MediaGridItem[];
    onFolderChange: (folderId: string) => void;
}

/** @private */
export interface UseMediaGridListenerReturn {
    selectedItems: Ref<MediaGridItem[]>;
    listSelectionStartItem: Ref<MediaGridItem | null>;
    mediaItemSelectionHandler: ComputedRef<{ [event: string]: (payload: MediaGridItemEvent) => void }>;
    isListSelect: ComputedRef<boolean>;
    isItemSelected: (itemToCompare: MediaGridItem) => boolean;
    showItemSelected: (item: MediaGridItem) => boolean;
    clearSelection: () => void;
    navigateToFolder: (payload: { item: MediaGridItem }) => void;
    showDetails: (gridItem: MediaGridItem) => void;
    handleMediaItemClicked: (payload: MediaGridItemEvent) => void;
    handleMediaGridItemSelected: (payload: MediaGridItemEvent) => void;
    handleMediaGridItemUnselected: (payload: { item: MediaGridItem }) => void;
}

/**
 * Composable alternative to the `media-grid-listener` mixin: owns click, ctrl and
 * shift selection of media grid items. The mixin emitted `media-folder-change` and
 * declared an empty `selectableItems` computed for the host to override; a
 * composable can do neither, so both are passed in as options.
 *
 * Keep this and `src/module/sw-media/mixin/media-grid-listener.mixin.js` in sync —
 * change both together.
 *
 * @private
 */
export function useMediaGridListener(options: UseMediaGridListenerOptions): UseMediaGridListenerReturn {
    const selectedItems = ref<MediaGridItem[]>([]);
    const listSelectionStartItem = ref<MediaGridItem | null>(null);

    const isListSelect = computed(() => listSelectionStartItem.value !== null);

    function isItemSelected(itemToCompare: MediaGridItem): boolean {
        const findIndex = selectedItems.value.findIndex((item) => {
            return item === itemToCompare;
        });

        return findIndex > -1;
    }

    function showItemSelected(item: MediaGridItem): boolean {
        return isItemSelected(item);
    }

    function clearSelection(): void {
        selectedItems.value = [];
        listSelectionStartItem.value = null;
    }

    function navigateToFolder({ item }: { item: MediaGridItem }): void {
        options.onFolderChange(item.id);
    }

    function startListSelect(item: MediaGridItem): void {
        selectedItems.value = [item];
        listSelectionStartItem.value = item;
    }

    function singleSelect(item: MediaGridItem): void {
        if (item.getEntityName() === 'media_folder') {
            navigateToFolder({ item });
        }

        selectedItems.value = [item];
        listSelectionStartItem.value = null;
    }

    function removeItemFromSelection(item: MediaGridItem): void {
        selectedItems.value = selectedItems.value.filter((currentSelected) => {
            return currentSelected !== item;
        });

        if (listSelectionStartItem.value === item) {
            listSelectionStartItem.value = selectedItems.value[0] || null;
        }
    }

    function addItemToSelection(item: MediaGridItem): void {
        if (!isListSelect.value) {
            if (selectedItems.value.length === 1) {
                startListSelect(selectedItems.value[0]);
                addItemToSelection(item);
                return;
            }

            startListSelect(item);
            return;
        }

        if (!isItemSelected(item)) {
            selectedItems.value.push(item);
        }
    }

    function findSelectionIndices(first: MediaGridItem | null, second: MediaGridItem): { start: number; end: number } {
        const selectable = options.selectableItems();

        const firstIndex = selectable.findIndex((selectableItem) => {
            return first === selectableItem;
        });

        const secondIndex = selectable.findIndex((selectableItem) => {
            return second === selectableItem;
        });

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

        const indices = findSelectionIndices(listSelectionStartItem.value, item);
        const selectable = options.selectableItems();

        selectedItems.value = selectable.slice(indices.start, indices.end + 1);
        listSelectionStartItem.value = selectable[indices.start];
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
