const { Mixin } = Shopware;

/**
 * Mixin to handle the selection of sw-media-media-item components.
 *
 * usage:
 *   - override selectableItems computed property and return array of entities that can be selected
 * @package content
 */
Mixin.register('media-grid-listener', {
    data() {
        return {
            selectedItems: [],
            listSelectionStartItem: null,
        };
    },

    computed: {
        mediaItemSelectionHandler() {
            return {
                'media-item-click': this.handleMediaItemClicked,
                'media-item-selection-add': this.handleMediaGridItemSelected,
                'media-item-selection-remove': this.handleMediaGridItemUnselected,
                'media-item-play': this.handleMediaItemClicked,
            };
        },

        isListSelect() {
            return this.listSelectionStartItem !== null;
        },

        selectableItems() {
            return [];
        },
    },

    methods: {
        isItemSelected(itemToCompare) {
            const findIndex = this.selectedItems.findIndex((item) => {
                return item === itemToCompare;
            });

            return findIndex > -1;
        },

        showItemSelected(item) {
            return this.isItemSelected(item);
        },

        clearSelection() {
            this.selectedItems = [];
            this.listSelectionStartItem = null;
        },

        navigateToFolder({ item }) {
            this.$emit('media-folder-change', item.id);
        },

        showDetails(gridItem) {
            this._singleSelect(gridItem);
        },

        handleMediaItemClicked({ originalDomEvent, item }) {
            if (originalDomEvent.shiftKey) {
                this._handleShiftSelect(item);
                return;
            }

            if (this.isListSelect || originalDomEvent.ctrlKey || originalDomEvent.metaKey) {
                this._handleSelection(item);
                return;
            }

            this._singleSelect(item);
        },

        handleMediaGridItemSelected({ originalDomEvent, item }) {
            if (originalDomEvent.shiftKey) {
                this._handleShiftSelect(item);
                return;
            }
            this._addItemToSelection(item);
        },

        handleMediaGridItemUnselected({ item }) {
            this._removeItemFromSelection(item);
        },

        _singleSelect(item) {
            if (item.getEntityName() === 'media_folder') {
                this.navigateToFolder({ item });
            }

            this.selectedItems = [item];
            this.listSelectionStartItem = null;
        },

        _startListSelect(item) {
            this.selectedItems = [item];
            this.listSelectionStartItem = item;
        },

        _handleSelection(item) {
            if (this.isItemSelected(item)) {
                this._removeItemFromSelection(item);
                return;
            }

            this._addItemToSelection(item);
        },

        _removeItemFromSelection(item) {
            this.selectedItems = this.selectedItems.filter((currentSelected) => {
                return currentSelected !== item;
            });

            if (this.listSelectionStartItem === item) {
                this.listSelectionStartItem = this.selectedItems[0] || null;
            }
        },

        _addItemToSelection(item) {
            if (!this.isListSelect) {
                if (this.selectedItems.length === 1) {
                    this._startListSelect(this.selectedItems[0]);
                    this._addItemToSelection(item);
                    return;
                }

                this._startListSelect(item);
                return;
            }

            if (!this.isItemSelected(item)) {
                this.selectedItems.push(item);
            }
        },

        _handleShiftSelect(item) {
            if (!this.isListSelect) {
                if (this.selectedItems.length === 1) {
                    this._startListSelect(this.selectedItems[0]);
                    this._handleShiftSelect(item);
                    return;
                }

                this._startListSelect(item);
                return;
            }

            if (item === this.listSelectionStartItem) {
                this._startListSelect(item);
                return;
            }

            const indices = this._findSelectionIndices(this.listSelectionStartItem, item);
            this.selectedItems = this.selectableItems.slice(indices.start, indices.end + 1);

            this.listSelectionStartItem = this.selectableItems[indices.start];
        },

        _findSelectionIndices(first, second) {
            const firstIndex = this.selectableItems.findIndex((selectableItem) => {
                return first === selectableItem;
            });

            const secondIndex = this.selectableItems.findIndex((selectableItem) => {
                return second === selectableItem;
            });

            return {
                start: Math.min(firstIndex, secondIndex),
                end: Math.max(firstIndex, secondIndex),
            };
        },
    },
});
