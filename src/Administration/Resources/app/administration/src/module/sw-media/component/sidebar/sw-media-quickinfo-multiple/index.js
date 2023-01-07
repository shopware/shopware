import template from './sw-media-quickinfo-multiple.html.twig';
import './sw-media-quickinfo-multiple.scss';

const { Mixin } = Shopware;

/**
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

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
            return `${this.$tc('sw-media.sidebar.metadata.totalSize')}: ${this.getFileSize}`;
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

        quickActionClassesDelete(disabled) {
            return ['sw-media-sidebar__quickaction', {
                'sw-media-sidebar__quickaction--disabled': disabled,
            }];
        },

        quickActionClasses(disabled) {
            return ['sw-media-sidebar__quickaction', {
                'sw-media-sidebar__quickaction--disabled': disabled,
            }];
        },
    },
};
