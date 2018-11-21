import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-media-grid-media-item.html.twig';
import './sw-media-grid-media-item.less';
import domUtils from '../../../../core/service/utils/dom.utils';

/**
 * @private
 */
Component.register('sw-media-grid-media-item', {
    template,

    inject: ['mediaService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        item: {
            required: true,
            type: Object,
            validator(value) {
                return value.type === 'media';
            }
        },

        showSelectionIndicator: {
            required: true,
            type: Boolean
        },

        selected: {
            type: Boolean,
            required: true
        },

        isList: {
            type: Boolean,
            required: false,
            default: false
        },

        showContextMenuButton: {
            type: Boolean,
            required: false,
            default: true
        }
    },

    data() {
        return {
            showModalReplace: false,
            showModalDelete: false,
            isInlineEdit: false,
            lastContent: ''
        };
    },

    computed: {
        locale() {
            return this.$root.$i18n.locale;
        },

        mediaPreviewClasses() {
            return {
                'is--highlighted': this.selected
            };
        },

        selectionIndicatorClasses() {
            return {
                'selected-indicator--visible': this.showSelectionIndicator,
                'selected-indicator--checked': this.listSelected
            };
        },

        defaultContextMenuClass() {
            return {
                'sw-context-menu__group': this.$slots['additional-context-menu-items']
            };
        },

        mediaStore() {
            return State.getStore('media');
        },

        listSelected() {
            return this.selected && this.showSelectionIndicator;
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
            const el = this.$refs.itemName;
            if (el.offsetWidth < el.scrollWidth) {
                this.lastContent = `${this.item.fileName.slice(-3)}.${this.item.fileExtension}`;
                return;
            }

            this.lastContent = '';
        },

        handleGridItemClick({ originalDomEvent }) {
            if (this.isSelectionIndicatorClicked(originalDomEvent.composedPath())) {
                return;
            }

            this.$emit('sw-media-grid-media-item-clicked', {
                originalDomEvent,
                item: this.item
            });
        },

        isSelectionIndicatorClicked(path) {
            return path.some((parent) => {
                return parent.classList && parent.classList.contains('sw-media-grid-media-item__selected-indicator');
            });
        },

        doSelectItem(originalDomEvent) {
            if (!this.listSelected) {
                this.selectItem(originalDomEvent);
                return;
            }

            this.removeFromSelection(originalDomEvent);
        },

        selectItem(originalDomEvent) {
            this.$emit('sw-media-grid-media-item-selection-add', {
                originalDomEvent,
                item: this.item
            });
        },

        removeFromSelection(originalDomEvent) {
            this.$emit('sw-media-grid-media-item-selection-remove', {
                originalDomEvent,
                item: this.item
            });
        },

        emitPlayEvent(originalDomEvent) {
            if (!this.selected) {
                this.$emit('sw-media-grid-media-item-play', {
                    originalDomEvent,
                    item: this.item
                });
                return;
            }

            this.removeFromSelection(originalDomEvent);
        },

        copyItemLink() {
            domUtils.copyToClipboard(this.item.url);
            this.createNotificationSuccess({ message: this.$tc('sw-media.general.notificationUrlCopied') });
        },

        openModalDelete() {
            this.showModalDelete = true;
        },

        closeModalDelete() {
            this.showModalDelete = false;
        },

        emitItemDeleted(deletePromise) {
            this.closeModalDelete();
            deletePromise.then(() => {
                this.$emit('sw-media-grid-media-item-delete');
            });
        },

        openModalReplace() {
            this.showModalReplace = true;
        },

        closeModalReplace() {
            this.showModalReplace = false;
        },

        startInlineEdit() {
            this.isInlineEdit = true;
        },

        endInlineEdit() {
            this.isInlineEdit = false;
        },

        updateName() {
            const inputField = this.$refs.inputItemName;

            if (!inputField.currentValue || !inputField.currentValue.trim()) {
                this.createNotificationError({
                    message: this.$tc('global.sw-media-grid-media-item.notificationErrorBlankItemName')
                });
                return;
            }

            this.item.isLoading = true;
            this.mediaService.renameMedia(this.item.id, inputField.currentValue).then(() => {
                this.mediaStore.getByIdAsync(this.item.id).then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc('global.sw-media-grid-media-item.notificationRenamingSuccess')
                    });
                    this.item.isLoading = false;
                    this.endInlineEdit();
                });
            }).catch(() => {
                this.item.isLoading = false;
                this.createNotificationError({
                    message: this.$tc('global.sw-media-grid-media-item.notificationRenamingError')
                });
                this.endInlineEdit();
            });
        }
    }
});
