import template from './sw-media-base-item.html.twig';
import './sw-media-base-item.scss';

/**
 * @status ready
 * @description The <u>sw-media-base-item</u> component is the base for items in the media manager.
 * @example-type code-only
 * @component-example
 * <sw-media-base-item
 *     isList="true"
 *     :isLoading="item.isLoading">
 * </sw-media-base-item>
 */
export default {
    name: 'sw-media-base-item',
    template,

    inject: [
        'renameEntity',
        'rejectRenaming'
    ],

    props: {
        isList: {
            type: Boolean,
            required: false,
            default: false
        },

        showSelectionIndicator: {
            required: false,
            type: Boolean,
            default: false
        },

        showContextMenuButton: {
            type: Boolean,
            required: false,
            default: true
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false
        },

        selected: {
            type: Boolean,
            required: false,
            default: false
        },

        displayName: {
            type: String,
            required: true
        },

        editValue: {
            type: String,
            required: true
        },

        editable: {
            type: Boolean,
            required: false,
            default: true
        },

        allowMultiSelect: {
            type: Boolean,
            required: false,
            default: true
        }
    },

    data() {
        return {
            isInlineEdit: false,
            lastContent: '',
            renamingCanceled: false
        };
    },

    computed: {
        mediaItemClasses() {
            return {
                'is--list': this.isList,
                'is--selected': this.selected || this.isInlineEdit
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
                'selected-indicator--is-allowed': this.allowMultiSelect
            };
        }
    },

    mounted() {
        this.componentMounted();
    },

    updated() {
        this.componentUpdated();
    },

    methods: {
        componentMounted() {
            this.computeLastContent();
        },

        componentUpdated() {
            this.computeLastContent();
        },

        computeLastContent() {
            if (this.isInlineEdit) {
                return;
            }

            const el = this.$refs.itemName;
            if (el.offsetWidth < el.scrollWidth) {
                this.lastContent = this.displayName.slice(-3);
                return;
            }

            this.lastContent = '';
        },

        handleItemClick(originalDomEvent) {
            if (this.isSelectionIndicatorClicked(originalDomEvent.composedPath())) {
                return;
            }
            this.$emit('sw-media-item-clicked', originalDomEvent);
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
            this.$emit('sw-media-item-selection-add', originalDomEvent);
        },

        removeFromSelection(originalDomEvent) {
            this.$emit('media-item-selection-remove', originalDomEvent);
        },

        startInlineEdit(event) {
            if (this.editable) {
                this.isInlineEdit = true;

                if (event) {
                    event.stopPropagation();
                }
            }
        },

        endInlineEdit() {
            this.isInlineEdit = false;
        },

        onCancelRenaming() {
            this.renamingCanceled = true;
            this.endInlineEdit();
            this.$nextTick(() => {
                this.rejectRenaming('canceled');
            });
        },

        onDoRenaming() {
            this.renamingCanceled = false;
            this.onBlurInlineEdit();
        },

        onBlurInlineEdit() {
            if (this.isInlineEdit === false) {
                return;
            }
            this.isInlineEdit = false;

            const inputField = this.$refs.inputItemName;
            if (!inputField.currentValue || !inputField.currentValue.trim()) {
                this.endInlineEdit();
                this.$nextTick(() => {
                    this.rejectRenaming('empty-name');
                });
                return;
            }

            this.renameEntity(inputField.currentValue).then(() => {
                this.endInlineEdit();
            }).catch(() => {
                this.endInlineEdit();
            });
        }
    }
};
