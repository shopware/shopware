import template from './sw-media-list-selection-v2.html.twig';
import './sw-media-list-selection-v2.scss';

const { Component, Mixin, Context } = Shopware;

Component.register('sw-media-list-selection-v2', {
    template,

    inject: ['repositoryFactory', 'mediaService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        entity: {
            type: Object,
            required: true,
        },

        entityMediaItems: {
            type: Array,
            required: true,
        },

        uploadTag: {
            type: String,
            required: false,
            default: null,
        },

        defaultFolderName: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            columnCount: 8,
            columnWidth: '90px',
        };
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

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

        gridAutoRows() {
            return `grid-auto-rows: ${this.columnWidth}`;
        },

        uploadId() {
            return this.uploadTag || this.entity.id;
        },

        defaultFolder() {
            return this.defaultFolderName || this.entity.getEntityName();
        },
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.$device.onResize({
                listener: this.updateColumnCount,
                component: this,
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
                    name: '',
                },
                mediaId: this.currentCount,
            });
        },

        async onUploadsAdded({ data }) {
            if (data.length === 0) {
                return;
            }

            this.entity.isLoading = true;

            await this.mediaService.runUploads(this.uploadId);
            this.entity.isLoading = false;
        },

        onMediaUploadButtonOpenSidebar() {
            this.$emit('open-sidebar');
        },

        async successfulUpload({ targetId }) {
            const mediaItem = await this.mediaRepository.get(targetId, Context.api);
            this.$forceUpdate();
            this.$emit('upload-finish', mediaItem);
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

        removeItem(mediaItem, index) {
            this.$emit('item-remove', mediaItem, index);
        },
    },
});
