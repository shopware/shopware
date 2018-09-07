import { Component, Mixin } from 'src/core/shopware';
import template from './sw-media-grid.html.twig';
import './sw-media-grid.less';

Component.register('sw-media-grid', {
    template,

    mixins: [
        Mixin.getByName('drag-selector')
    ],

    props: {
        previewType: {
            required: true,
            type: String,
            validator(value) {
                return [
                    'media-grid-preview-as-grid',
                    'media-grid-preview-as-list'
                ].includes(value);
            }
        },

        previewComponent: {
            require: true,
            type: String
        },

        items: {
            required: true,
            type: Array
        },

        idField: {
            required: false,
            default: 'id',
            type: String
        },

        editable: {
            required: false,
            type: Boolean,
            default: false
        },

        selectable: {
            required: false,
            type: Boolean,
            default: true
        },

        gridColumnWidth: {
            required: false,
            type: Number,
            default: 200,
            validator(value) {
                return value > 0;
            }
        }
    },

    data() {
        return {
            selection: [],
            listSelectionStartItem: null
        };
    },

    computed: {
        dragSelectorClass() {
            return 'sw-media-grid-item';
        },

        mediaColumnDefinitions() {
            let columnDefinition;

            switch (this.previewType) {
            case 'media-grid-preview-as-list':
                columnDefinition = '100%';
                break;

            case 'media-grid-preview-as-grid':
            default:
                columnDefinition = `repeat(auto-fit, ${this.gridColumnWidth}px)`;
            }

            return {
                'grid-template-columns': columnDefinition
            };
        },

        showSelectedIndicator() {
            return this.selectable && this.selection.length > 0;
        },

        containerOptions() {
            return {
                previewType: this.previewType,
                selectionInProgress: this.showSelectedIndicator,
                previewSize: this.gridColumnWidth,
                selectable: this.selectable,
                editable: this.editable
            };
        }
    },

    created() {
        this.componentCreated();
    },

    beforeDestroy() {
        this.beforeComponentDestroyed();
    },

    watch: {
        items() {
            this.clearSelection();
        }
    },

    methods: {
        componentCreated() {
            window.addEventListener('click', this.clearSelectionOnClickOutside, false);
        },

        beforeComponentDestroyed() {
            window.removeEventListener('click', this.clearSelectionOnClickOutside);
        },

        clearSelectionOnClickOutside(event) {
            const target = event.target;

            const clickedChildren = this.$children.filter((child) => {
                return child.$el === target || child.$el.contains(target);
            });

            if (clickedChildren.length === 0 && !target.classList.contains('sw-context-menu-item')) {
                this.emitSelectionCleared(event);
            }
        },

        getSelection() {
            return this.selection;
        },

        clearSelection() {
            this.selection = [];
        },

        emitSelectionCleared(originalDomEvent) {
            this.clearSelection();
            this.$emit('sw-media-grid-selection-clear', {
                originalDomEvent
            });
        },

        isItemSelected(item) {
            if (this.selection.length === 0) {
                return false;
            }
            return this.findIndexInSelection(item) > -1;
        },

        mediaItemClicked({ originalDomEvent, item }) {
            this.handleClick({ originalDomEvent, item, autoplay: false });
        },

        mediaItemPlayed({ originalDomEvent, item }) {
            this.handleClick({ originalDomEvent, item, autoplay: true });
        },

        handleClick({ originalDomEvent, item, autoplay }) {
            if (this.selection.length > 0 || originalDomEvent.ctrlKey || originalDomEvent.shiftKey) {
                this.handleSelection({ originalDomEvent, item });
                return;
            }

            this.$emit('sw-media-grid-media-item-show-details', {
                originalDomEvent,
                item,
                autoplay
            });
        },

        handleSelection({ originalDomEvent, item }) {
            if (originalDomEvent && originalDomEvent.shiftKey) {
                this.listSelect({ originalDomEvent, item });
                return;
            }

            this.addToSelection({ originalDomEvent, item });
        },

        singleSelect({ originalDomEvent, item }) {
            this.emitSelectionCleared();
            this.addToSelection({ originalDomEvent, item });
        },

        addToSelection({ originalDomEvent, item }) {
            if (this.selectable) {
                if (!this.isItemSelected(item)) {
                    this.selection.push(item);
                }
            }

            this.$emit('sw-media-grid-item-selection-add', {
                originalDomEvent,
                item
            });
        },

        listSelect({ originalDomEvent, item }) {
            if (this.listSelectionStartItem === item) {
                return;
            }

            if (!this.listSelectionStartItem) {
                this.listSelectionStartItem = item;
                this.addToSelection({ originalDomEvent, item });
                return;
            }

            const result = this.getSelectedIndexes(this.listSelectionStartItem, item);

            for (let i = result.startIndex; i <= result.endIndex; i += 1) {
                const listItem = this.items[i];
                this.addToSelection({ originalDomEvent, item: listItem });
            }
            this.listSelectionStartItem = null;
        },

        getSelectedIndexes(startItem, endItem) {
            let startIndex = this.findIndexInItems(startItem);
            let endIndex = this.findIndexInItems(endItem);
            if (endIndex < startIndex) {
                const tmp = endIndex;
                endIndex = startIndex;
                startIndex = tmp;
            }

            return { endIndex, startIndex };
        },

        findIndexInSelection(item) {
            return this.selection.findIndex((element) => {
                return (element[this.idField] === item[this.idField]);
            });
        },

        findIndexInItems(item) {
            return this.items.findIndex((element) => {
                return (element[this.idField] === item[this.idField]);
            });
        },

        removeFromSelection({ originalDomEvent, item }) {
            this.selection = this.selection.filter((element) => {
                return !(element[this.idField] === item[this.idField]);
            });

            this.$emit('sw-media-grid-item-selection-remove', {
                originalDomEvent,
                item
            });
        },

        onDragSelection({ originalDomEvent, item }) {
            item.selectItem(originalDomEvent);
        },

        onDragDeselection({ originalDomEvent, item }) {
            item.removeFromSelection(originalDomEvent);
        }
    }
});
