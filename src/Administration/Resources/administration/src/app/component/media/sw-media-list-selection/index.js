import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-media-list-selection.html.twig';
import './sw-media-list-selection.scss';

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
        }
    },

    data() {
        return {
            columnCount: 8,
            columnWidth: 90,
            entityMediaImages: this.entityMediaItems
        };
    },

    watch: {
        entityMediaItems: {
            handler() {
                this.entityMediaImages = this.entityMediaItems;
            },
            deep: true
        }
    },

    computed: {
        mediaItems() {
            const mediaItems = this.entityMediaImages.slice();

            const placeholderCount = this.getPlaceholderCount(this.columnCount);
            if (placeholderCount === 0) {
                return mediaItems;
            }

            for (let i = 0; i < placeholderCount; i += 1) {
                mediaItems.push(this.createPlaceholderMedia(mediaItems));
            }
            return mediaItems;
        },

        uploadStore() {
            return State.getStore('upload');
        },

        mediaStore() {
            return State.getStore('media');
        },

        gridAutoRows() {
            return `grid-auto-rows: ${this.columnWidth}`;
        },

        uploadId() {
            return this.uploadTag || this.entity.id;
        }
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            const that = this;
            this.$device.onResize({
                listener() {
                    that.updateColumnCount();
                },
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

        getPlaceholderCount(columnCount) {
            if (this.entityMediaImages.length + 2 < columnCount * 2) {
                columnCount *= 2;
            }
            const placeholderCount = columnCount - ((this.entityMediaImages.length + 2) % columnCount);

            if (placeholderCount === columnCount) {
                return 0;
            }

            return placeholderCount;
        },

        createPlaceholderMedia(mediaItems) {
            return {
                isPlaceholder: true,
                media: {
                    isPlaceholder: true,
                    name: ''
                },
                mediaId: mediaItems.length
            };
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

        getKey(media) {
            return media.id;
        },

        successfulUpload({ targetId }) {
            this.mediaStore.getByIdAsync(targetId).then((mediaItem) => {
                this.$forceUpdate();
                this.$emit('upload-finish', mediaItem);
            });
        },

        onUploadFailed(uploadTask) {
            const toRemove = this.entityMediaImages.find((media) => {
                return media.mediaId === uploadTask.targetId;
            });

            if (toRemove) {
                this.removeItem(toRemove);
            }

            this.entity.isLoading = false;
        },

        removeItem(mediaItem) {
            const key = mediaItem.id;
            this.entityMediaImages = this.entityMediaImages.filter((e) => e.id !== key && e !== key);

            this.$emit('item-remove', mediaItem);
        }
    }
});
