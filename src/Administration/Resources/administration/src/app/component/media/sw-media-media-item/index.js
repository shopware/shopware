import template from './sw-media-media-item.html.twig';
import './sw-media-media-item.scss';

const { Component, Mixin, StateDeprecated } = Shopware;
const domUtils = Shopware.Utils.dom;

/**
 * @status ready
 * @description The <u>sw-media-media-item</u> component is used to store the media item and manage it through the
 * <u>sw-media-base-item</u> component. Use the default slot to add additional context menu items.
 * @example-type code-only
 * @component-example
 * <sw-media-media-item
 *     :key="mediaItem.id"
 *     :item="mediaItem"
 *     :selected="false"
 *     :showSelectionIndicator="false"
 *     :isList="false">
 *
 *       <sw-context-menu-item @click="showDetails(mediaItem)"
 *             slot="additional-context-menu-items">
 *          Lorem ipsum dolor sit amet
 *       </sw-context-menu-item>
 * </sw-media-media-item>
 */
Component.register('sw-media-media-item', {
    template,
    inheritAttrs: false,

    inject: ['mediaService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            showModalReplace: false,
            showModalDelete: false,
            showModalMove: false
        };
    },

    computed: {
        locale() {
            return this.$root.$i18n.locale;
        },

        defaultContextMenuClass() {
            return {
                'sw-context-menu__group': this.$slots.default
            };
        },

        mediaStore() {
            return StateDeprecated.getStore('media');
        }
    },

    methods: {
        onChangeName(updatedName, item, endInlineEdit) {
            if (!updatedName || !updatedName.trim()) {
                this.rejectRenaming(endInlineEdit);
                return;
            }

            item.isLoading = true;
            this.mediaService.renameMedia(item.id, updatedName).then(() => {
                this.mediaStore.getByIdAsync(item.id).then(() => {
                    this.createNotificationSuccess({
                        title: this.$tc('global.default.success'),
                        message: this.$tc('global.sw-media-media-item.notification.renamingSuccess.message')
                    });
                });

                endInlineEdit();
                this.$emit('media-item-rename-success', item);
            }).catch(() => {
                item.isLoading = false;
                endInlineEdit();
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('global.sw-media-media-item.notification.renamingError.message')
                });
            });
        },

        rejectRenaming(endInlineEdit) {
            this.createNotificationError({
                title: this.$tc('global.default.error'),
                message: this.$tc('global.sw-media-media-item.notification.errorBlankItemName.message')
            });

            endInlineEdit();
        },

        onBlur(event, item, endInlineEdit) {
            const input = event.target.value;
            if (input !== item.fileName) {
                return;
            }

            if (!input || !input.trim()) {
                this.rejectRenaming(item, 'empty-name', endInlineEdit);
                return;
            }

            endInlineEdit();
        },

        emitPlayEvent(originalDomEvent, item) {
            if (!this.selected) {
                this.$emit('media-item-play', {
                    originalDomEvent,
                    item
                });
                return;
            }

            this.removeFromSelection(originalDomEvent);
        },

        copyItemLink(item) {
            domUtils.copyToClipboard(item.url);
            this.createNotificationSuccess({
                title: this.$tc('sw-media.general.notification.urlCopied.title'),
                message: this.$tc('sw-media.general.notification.urlCopied.message')
            });
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
                this.$emit('media-item-delete', ids.mediaIds);
            });
        },

        openModalReplace() {
            this.showModalReplace = true;
        },

        closeModalReplace() {
            this.showModalReplace = false;
        },

        openModalMove() {
            this.showModalMove = true;
        },

        closeModalMove() {
            this.showModalMove = false;
        },

        onMediaItemMoved(movePromise) {
            this.closeModalMove();
            movePromise.then((ids) => {
                this.$emit('media-folder-move', ids);
            });
        }
    }
});
