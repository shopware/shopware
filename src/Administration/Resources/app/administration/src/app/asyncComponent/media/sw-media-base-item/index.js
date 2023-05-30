import template from './sw-media-base-item.html.twig';
import './sw-media-base-item.scss';

/**
 * @status ready
 * @description The <u>sw-media-base-item</u> component is the base for items in the media manager.
 * @package content
 * @example-type code-only
 * @component-example
 * <sw-media-base-item
 *     :item="myItem"
 *     :is-list="true">
 * </sw-media-base-item>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        item: {
            type: Object,
            required: true,
        },

        isList: {
            type: Boolean,
            required: false,
            default: false,
        },

        showSelectionIndicator: {
            required: false,
            type: Boolean,
            default: false,
        },

        showContextMenuButton: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        selected: {
            type: Boolean,
            required: false,
            default: false,
        },

        editable: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        allowMultiSelect: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        truncateRight: {
            type: Boolean,
            required: false,
            default: false,
        },

        allowEdit: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        allowDelete: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    data() {
        return {
            isInlineEdit: false,
        };
    },

    computed: {
        mediaItemClasses() {
            return {
                'is--list': this.isList,
                'is--selected': this.selected || this.isInlineEdit,
            };
        },

        mediaNameContainerClasses() {
            return {
                'is--truncate-right': this.truncateRight,
            };
        },

        listSelected() {
            return this.selected && this.showSelectionIndicator;
        },

        selectionIndicatorClasses() {
            return {
                'selected-indicator--visible': this.showSelectionIndicator,
                'selected-indicator--list': this.isList,
                'selected-indicator--checked': this.listSelected,
                'selected-indicator--is-allowed': this.allowMultiSelect,
            };
        },

        isLoading() {
            return this.item.isLoading;
        },
    },

    methods: {
        handleItemClick(originalDomEvent) {
            if (this.isSelectionIndicatorClicked(originalDomEvent.composedPath())) {
                return;
            }
            this.$emit('media-item-click', {
                originalDomEvent,
                item: this.item,
            });
        },

        isSelectionIndicatorClicked(path) {
            return path.some((parent) => {
                return parent.classList && (
                    parent.classList.contains('sw-media-base-item__selected-indicator') ||
                    parent.classList.contains('sw-context-button')
                );
            });
        },

        onClickedItem(originalDomEvent) {
            if (!this.listSelected || !this.allowMultiSelect) {
                this.selectItem(originalDomEvent);
                return;
            }
            this.removeFromSelection(originalDomEvent);
        },

        selectItem(originalDomEvent) {
            this.$emit('media-item-selection-add', {
                originalDomEvent,
                item: this.item,
            });
        },

        removeFromSelection(originalDomEvent) {
            this.$emit('media-item-selection-remove', {
                originalDomEvent,
                item: this.item,
            });
        },

        startInlineEdit() {
            if (this.editable && this.allowEdit) {
                this.isInlineEdit = true;
            }
        },

        endInlineEdit() {
            this.isInlineEdit = false;
        },
    },
};
