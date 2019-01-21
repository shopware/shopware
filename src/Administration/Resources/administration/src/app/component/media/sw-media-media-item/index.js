import { Filter, Mixin, State } from 'src/core/shopware';
import domUtils from 'src/core/service/utils/dom.utils';
import template from './sw-media-media-item.html.twig';
import './sw-media-media-item.less';

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
export default {
    name: 'sw-media-media-item',
    template,

    inject: ['mediaService'],

    provide() {
        return {
            renameEntity: this.renameEntity,
            rejectRenaming: this.rejectRenaming
        };
    },

    mixins: [
        Mixin.getByName('selectable-media-item'),
        Mixin.getByName('notification')
    ],

    props: {
        item: {
            type: Object,
            required: true,
            validator(value) {
                return value.entityName === 'media';
            }
        }
    },

    data() {
        return {
            showModalReplace: false,
            showModalDelete: false
        };
    },

    computed: {
        locale() {
            return this.$root.$i18n.locale;
        },

        mediaPreviewClasses() {
            return {
                'sw-media-preview--list': this.isList
            };
        },

        defaultContextMenuClass() {
            return {
                'sw-context-menu__group': this.$slots.default
            };
        },

        mediaStore() {
            return State.getStore('media');
        },

        displayName() {
            if (this.item.hasFile) {
                return `${this.item.fileName}.${this.item.fileExtension}`;
            }

            return this.item.isLoading ? this.$tc('global.sw-media-media-item.labelUploading') : '';
        },

        editFileName() {
            return this.item.fileName || '';
        },

        baseComponent() {
            return this.$refs.innerComponent;
        },

        dateFilter() {
            return Filter.getByName('date');
        },

        fileSizeFilter() {
            return Filter.getByName('fileSize');
        },

        getMetaData() {
            const metadata = [
                this.dateFilter(this.item.uploadedAt, this.locale, { year: 'numeric', month: 'long', day: 'numeric' })
            ];

            if (this.item.fileSize) {
                metadata.push(this.fileSizeFilter(this.item.fileSize, this.locale));
            }

            return metadata.join(', ');
        }
    },

    methods: {
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

        onStartRenaming() {
            this.baseComponent.startInlineEdit();
        },

        renameEntity(updatedName) {
            if (this.item.fileName === updatedName) {
                return Promise.resolve();
            }

            this.item.isLoading = true;
            return this.mediaService.renameMedia(this.item.id, updatedName).then(() => {
                this.mediaStore.getByIdAsync(this.item.id).then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc('global.sw-media-media-item.notificationRenamingSuccess')
                    });
                });
            }).catch(() => {
                this.item.isLoading = false;
                this.createNotificationError({
                    message: this.$tc('global.sw-media-media-item.notificationRenamingError')
                });
            });
        },

        rejectRenaming(cause) {
            if (cause === 'empty-name') {
                this.createNotificationError({
                    message: this.$tc('global.sw-media-media-item.notificationErrorBlankItemName')
                });
            }
        }
    }
};
