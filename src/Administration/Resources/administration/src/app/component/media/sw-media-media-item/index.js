import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-media-media-item.html.twig';
import './sw-media-media-item.less';
import domUtils from '../../../../core/service/utils/dom.utils';

/**
 * @private
 */
Component.register('sw-media-media-item', {
    template,

    inject: ['mediaService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        item: {
            type: Object,
            required: true,
            validator(value) {
                return value.type === 'media';
            }
        },

        /*
         * propagated props
         */
        showSelectionIndicator: {
            type: Boolean,
            required: true
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

        defaultContextMenuClass() {
            return {
                'sw-context-menu__group': this.$slots['additional-context-menu-items']
            };
        },

        mediaStore() {
            return State.getStore('media');
        },

        fallbackName() {
            return this.item.isLoading ? this.$tc('global.sw-media-media-item.labelUploading') : '';
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
                this.lastContent = `${this.item.fileName.slice(-3)}.${this.item.fileExtension}`;
                return;
            }

            this.lastContent = '';
        },

        handleGridItemClick(originalDomEvent) {
            this.$emit('sw-media-item-clicked', {
                originalDomEvent,
                item: this.item
            });
        },

        selectItem(originalDomEvent) {
            this.$emit('sw-media-item-selection-add', {
                originalDomEvent,
                item: this.item
            });
        },

        removeFromSelection(originalDomEvent) {
            this.$emit('sw-media-item-selection-remove', {
                originalDomEvent,
                item: this.item
            });
        },

        emitPlayEvent(originalDomEvent) {
            if (!this.selected) {
                this.$emit('sw-media-media-item-play', {
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
            deletePromise.then((ids) => {
                this.$emit('sw-media-media-item-delete', ids);
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
                    message: this.$tc('global.sw-media-media-item.notificationErrorBlankItemName')
                });
                return;
            }

            this.item.isLoading = true;
            this.mediaService.renameMedia(this.item.id, inputField.currentValue).then(() => {
                this.mediaStore.getByIdAsync(this.item.id).then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc('global.sw-media-media-item.notificationRenamingSuccess')
                    });
                    this.item.isLoading = false;
                    this.endInlineEdit();
                });
            }).catch(() => {
                this.item.isLoading = false;
                this.createNotificationError({
                    message: this.$tc('global.sw-media-media-item.notificationRenamingError')
                });
                this.endInlineEdit();
            });
        }
    }
});
