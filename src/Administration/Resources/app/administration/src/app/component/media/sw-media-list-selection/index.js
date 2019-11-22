import template from './sw-media-list-selection.html.twig';
import './sw-media-list-selection.scss';

const { Component, Mixin, StateDeprecated } = Shopware;

Component.register('sw-media-list-selection', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        entity: {
            type: Object,
            required: true
        },

        entityMediaItems: {
            type: Array,
            required: true
        },

        uploadTag: {
            type: String,
            required: false,
            default: null
        },

        defaultFolderName: {
            type: String,
            required: false,
            default: null
        }
    },

    data() {
        return {
            columnCount: 8,
            columnWidth: '90px'
        };
    },

    computed: {
        currentCount() {
            return this.entityMediaItems.length;
        },

        mediaItems() {
            // two rows with columnCount columns
            const columnCount = this.columnCount * 2;
            if (this.currentCount >= columnCount) {
                return this.entityMediaItems;
            }

            const items = [...this.entityMediaItems];
            items.splice(this.currentCount, 0, ...this.createPlaceholders(columnCount - this.currentCount));

            return items;
        },

        uploadStore() {
            return StateDeprecated.getStore('upload');
        },

        mediaStore() {
            return StateDeprecated.getStore('media');
        },

        gridAutoRows() {
            return `grid-auto-rows: ${this.columnWidth}`;
        },

        uploadId() {
            return this.uploadTag || this.entity.id;
        },

        defaultFolder() {
            return this.defaultFolderName || this.entity.getEntityName();
        }
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.$device.onResize({
                listener: this.updateColumnCount,
                component: this
            });
            this.updateColumnCount();
        },

        updateColumnCount() {
            this.$nextTick(() => {
                const cssColumns = window.getComputedStyle(this.$refs.grid, null)
                    .getPropertyValue('grid-template-columns')
                    .split(' ');
                this.columnCount = cssColumns.length;
                this.columnWidth = cssColumns[0];
            });
        },

        createPlaceholders(count) {
            return (new Array(count)).fill({
                isPlaceholder: true,
                media: {
                    isPlaceholder: true,
                    name: ''
                },
                mediaId: this.currentCount
            });
        },

        onUploadsAdded({ data }) {
            if (data.length === 0) {
                return;
            }

            this.entity.isLoading = true;
            this.mediaStore.sync().then(() => {
                this.entity.isLoading = false;
                this.uploadStore.runUploads(this.uploadId);
            });
        },

        onMediaUploadButtonOpenSidebar() {
            this.$emit('open-sidebar');
        },

        successfulUpload({ targetId }) {
            this.mediaStore.getByIdAsync(targetId).then((mediaItem) => {
                this.$forceUpdate();
                this.$emit('upload-finish', mediaItem);
            });
        },

        onUploadFailed(uploadTask) {
            const toRemove = this.mediaItems.find((media) => {
                return media.mediaId === uploadTask.targetId;
            });

            if (toRemove) {
                this.removeItem(toRemove);
            }

            this.entity.isLoading = false;
        },

        removeItem(mediaItem) {
            this.$emit('item-remove', mediaItem);
        }
    }
});
