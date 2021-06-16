import template from './sw-media-media-item.html.twig';
import './sw-media-media-item.scss';

const { Component, Mixin } = Shopware;
const { dom } = Shopware.Utils;

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
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            showModalReplace: false,
            showModalDelete: false,
            showModalMove: false,
        };
    },

    computed: {
        locale() {
            return this.$root.$i18n.locale;
        },

        defaultContextMenuClass() {
            return {
                'sw-context-menu__group': this.$slots.default,
            };
        },
    },

    methods: {
        async onChangeName(updatedName, item, endInlineEdit) {
            if (!updatedName || !updatedName.trim()) {
                this.rejectRenaming(endInlineEdit);
                return;
            }

            item.isLoading = true;

            try {
                await this.mediaService.renameMedia(item.id, updatedName);
                item.fileName = updatedName;
                item.isLoading = false;
                this.createNotificationSuccess({
                    message: this.$tc('global.sw-media-media-item.notification.renamingSuccess.message'),
                });
                this.$emit('media-item-rename-success', item);
            } catch {
                this.createNotificationError({
                    message: this.$tc('global.sw-media-media-item.notification.renamingError.message'),
                });
            } finally {
                item.isLoading = false;
                endInlineEdit();
            }
        },

        rejectRenaming(endInlineEdit) {
            this.createNotificationError({
                message: this.$tc('global.sw-media-media-item.notification.errorBlankItemName.message'),
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
                    item,
                });
                return;
            }

            this.removeFromSelection(originalDomEvent);
        },

        copyItemLink(item) {
            dom.copyToClipboard(item.url);
            this.createNotificationSuccess({
                message: this.$tc('sw-media.general.notification.urlCopied.message'),
            });
        },

        openModalDelete() {
            this.showModalDelete = true;
        },

        closeModalDelete() {
            this.showModalDelete = false;
        },

        async emitItemDeleted(deletePromise) {
            this.closeModalDelete();
            const ids = await deletePromise;
            this.$emit('media-item-delete', ids.mediaIds);
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

        async onMediaItemMoved(movePromise) {
            this.closeModalMove();
            const ids = await movePromise;
            this.$emit('media-folder-move', ids);
        },

        emitRefreshMediaLibrary() {
            this.closeModalReplace();

            this.$nextTick(() => {
                this.$emit('media-item-replaced');
            });
        },
    },
});
