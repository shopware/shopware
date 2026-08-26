import template from './sw-media-quickinfo-multiple.html.twig';
import './sw-media-quickinfo-multiple.scss';

const { Mixin } = Shopware;

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: ['media-item-selection-remove'],

    mixins: [
        Mixin.getByName('media-sidebar-modal-mixin'),
    ],

    props: {
        items: {
            required: true,
            type: Array,
        },

        editable: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        itemsIsAvailable() {
            return this.items.length > 0;
        },

        getFileSize() {
            const sizeInByte = this.items.reduce((value, items) => {
                return value + (items.fileSize || 0);
            }, 0);

            return Shopware.Utils.format.fileSize(sizeInByte);
        },

        getFileSizeLabel() {
            return `${this.$t('sw-media.sidebar.metadata.totalSize')}: ${this.getFileSize}`;
        },

        hasFolder() {
            return this.items.some((item) => {
                return item.getEntityName() === 'media_folder';
            });
        },

        hasMedia() {
            return this.items.some((item) => {
                return item.getEntityName() === 'media';
            });
        },

        mediaItems() {
            return this.items.filter((item) => {
                return item.getEntityName() === 'media';
            });
        },

        extensionSdkButtons() {
            if (this.mediaItems.length === 0) {
                return [];
            }

            return Shopware.Store.get('actionButtons').buttons.filter((button) => {
                if (button.entity !== 'media' || button.view !== 'list') {
                    return false;
                }

                return !button.fileTypes?.length || this.mediaItems.every((item) => this.matchesFileTypes(button, item));
            });
        },

        isPrivate() {
            return this.items.some((item) => {
                return item.private === true;
            });
        },
    },

    methods: {
        onRemoveItemFromSelection(event) {
            this.$emit('media-item-selection-remove', event);
        },

        matchesFileTypes(action, item) {
            return (
                !action.fileTypes?.length ||
                (!!item.fileExtension &&
                    action.fileTypes.some((type) => type.toLowerCase() === item.fileExtension.toLowerCase()))
            );
        },

        runAppAction(action) {
            if (typeof action.callback !== 'function') {
                return;
            }

            const items = this.mediaItems.map(({ id, url, fileName, mimeType, fileSize }) => {
                return { id, url, fileName, mimeType, fileSize };
            });

            action.callback(items);
        },

        quickActionClassesDelete(disabled) {
            return [
                'sw-media-sidebar__quickaction',
                {
                    'sw-media-sidebar__quickaction--disabled': disabled,
                },
            ];
        },

        quickActionClasses(disabled) {
            return [
                'sw-media-sidebar__quickaction',
                {
                    'sw-media-sidebar__quickaction--disabled': disabled,
                },
            ];
        },
    },
};
