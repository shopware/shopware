import template from './sw-media-media-item.html.twig';
import './sw-media-media-item.scss';
import 'src/module/sw-media/mixin/video-cover.mixin';

const { Mixin } = Shopware;
const { dom } = Shopware.Utils;

/**
 * @status ready
 * @description The <u>sw-media-media-item</u> component is used to store the media item and manage it through the
 * <u>sw-media-base-item</u> component. Use the default slot to add additional context menu items.
 * @sw-package discovery
 * @example-type code-only
 * @component-example
 * <sw-media-media-item
 *     :key="mediaItem.id"
 *     :item="mediaItem"
 *     :selected="false"
 *     :showSelectionIndicator="false"
 *     :isList="false">
 *
 *       <sw-context-menu-item
 *            #additional-context-menu-items
 *            \@click="showDetails(mediaItem)">
 *          Lorem ipsum dolor sit amet
 *       </sw-context-menu-item>
 * </sw-media-media-item>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inheritAttrs: false,

    inject: [
        'mediaService',
        'acl',
    ],

    props: {
        item: {
            type: Object,
            required: true,
        },
    },

    emits: [
        'media-item-rename-success',
        'media-item-play',
        'media-item-delete',
        'media-folder-move',
        'media-item-replaced',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('video-cover'),
    ],

    data() {
        return {
            showModalReplace: false,
            showModalDelete: false,
            showModalMove: false,
            showCoverSelectionModal: false,
            isSmallPreviewName: false,
        };
    },

    mounted() {
        this.updateNamePreviewMode();
    },

    updated() {
        this.updateNamePreviewMode();
    },

    computed: {
        locale() {
            return this.$root.$i18n.locale.value;
        },

        defaultContextMenuClass() {
            return {
                'sw-context-menu__group': this.$slots.default,
            };
        },

        mediaNameFilter() {
            return Shopware.Filter.getByName('mediaName');
        },

        mediaDisplayName() {
            return this.getMediaDisplayName(this.item);
        },

        mediaDisplayNameParts() {
            const suffixLength = 12;

            if (this.mediaDisplayName.length <= suffixLength * 2) {
                return null;
            }

            return {
                start: this.mediaDisplayName.slice(0, -suffixLength),
                end: this.mediaDisplayName.slice(-suffixLength),
            };
        },

        mediaDisplayDetails() {
            const details = [
                this.item.fileExtension?.toUpperCase(),
                this.mediaDisplayFileSize,
            ].filter(Boolean);

            return details.join(' • ');
        },

        mediaDisplayFileSize() {
            if (!this.item.fileSize) {
                return null;
            }

            return this.fileSizeFilter(this.item.fileSize, this.locale).replace(/([\d.,])([A-Z])/u, '$1 $2');
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, because the filter is unused
         */
        dateFilter() {
            return Shopware.Filter.getByName('date');
        },

        fileSizeFilter() {
            return Shopware.Filter.getByName('fileSize');
        },

        extensionSdkButtons() {
            return Shopware.Store.get('actionButtons').buttons.filter((button) => {
                if (button.entity !== 'media' || button.view !== 'item') {
                    return false;
                }

                return (
                    !button.fileTypes?.length ||
                    button.fileTypes.some((type) => {
                        return this.item?.fileExtension && type.toLowerCase() === this.item.fileExtension.toLowerCase();
                    })
                );
            });
        },

        mediaRepository() {
            return Shopware.Service('repositoryFactory').create('media');
        },
    },

    methods: {
        shouldUseMiddleTruncation(isList) {
            return Boolean(this.mediaDisplayNameParts && !isList && !this.isSmallPreviewName);
        },

        updateNamePreviewMode() {
            this.$nextTick(() => {
                const nameElement = this.$refs.itemName;

                if (!nameElement) {
                    this.isSmallPreviewName = false;
                    return;
                }

                this.isSmallPreviewName = Boolean(nameElement.closest('.sw-media-grid__presentation--small-preview'));
            });
        },

        getMediaDisplayName(item) {
            if (!item?.fileName) {
                return this.mediaNameFilter(item);
            }

            if (!item.fileExtension) {
                return item.fileName;
            }

            const extension = `.${item.fileExtension}`;

            if (item.fileName.toLowerCase().endsWith(extension.toLowerCase())) {
                return item.fileName.slice(0, -extension.length);
            }

            return item.fileName;
        },

        async onChangeName(updatedName, item, endInlineEdit) {
            if (!updatedName || !updatedName.trim()) {
                this.rejectRenaming(endInlineEdit);
                return;
            }

            item.isLoading = true;

            try {
                await this.mediaService.renameMedia(item.id, updatedName);
                await this.mediaRepository.get(item.id).then((response) => {
                    Object.assign(item, response);
                });

                item.isLoading = false;
                this.createNotificationSuccess({
                    message: this.$t('global.sw-media-media-item.notification.renamingSuccess.message'),
                });
                this.$emit('media-item-rename-success', item);
            } catch (exception) {
                const errors = exception.response.data.errors;

                errors.forEach((error) => {
                    this.handleErrorMessage(error);
                });
            } finally {
                item.isLoading = false;
                endInlineEdit();
            }
        },

        handleErrorMessage(error) {
            switch (error.code) {
                case 'CONTENT__MEDIA_FILE_NAME_IS_TOO_LONG':
                    this.createNotificationError({
                        message: this.$t(
                            'global.sw-media-media-item.notification.fileNameTooLong.message',
                            {
                                length: error.meta.parameters.maxLength,
                            },
                            0,
                        ),
                    });
                    break;
                default:
                    this.createNotificationError({
                        message: this.$t('global.sw-media-media-item.notification.renamingError.message'),
                    });
            }
        },

        rejectRenaming(endInlineEdit) {
            this.createNotificationError({
                message: this.$t('global.sw-media-media-item.notification.errorBlankItemName.message'),
            });

            endInlineEdit();
        },

        onBlur(event, item, endInlineEdit) {
            const input = event.target.value;

            if (input !== item.fileName) {
                this.onChangeName(input, item, endInlineEdit);
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

        async copyItemLink(item) {
            try {
                await dom.copyStringToClipboard(item.url);
                this.createNotificationSuccess({
                    message: this.$t('sw-media.general.notification.urlCopied.message'),
                });
            } catch (_err) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: this.$t('global.sw-field.notification.notificationCopyFailureMessage'),
                });
            }
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

        runAppAction(action, item) {
            if (typeof action.callback !== 'function') {
                return;
            }

            const { fileName, mimeType, fileSize, url, id } = item;

            action.callback({
                id,
                url,
                fileName,
                mimeType,
                fileSize,
            });
        },
    },
};
