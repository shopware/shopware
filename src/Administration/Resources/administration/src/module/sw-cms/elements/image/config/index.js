import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-cms-el-config-image.html.twig';
import './sw-cms-el-config-image.scss';

Component.register('sw-cms-el-config-image', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
    ],

    data() {
        return {
            mediaModalIsOpen: false,
            initialFolderId: null
        };
    },

    computed: {
        mediaStore() {
            return State.getStore('media');
        },

        uploadStore() {
            return State.getStore('upload');
        },

        uploadTag() {
            return `cms-element-media-config-${this.element.id}`;
        },

        previewSource() {
            if (this.element.data && this.element.data.media && this.element.data.media.id) {
                return this.element.data.media;
            }

            return this.element.config.media.value;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('image');
        },

        onChangeMedia() {
            return this.uploadStore.runUploads(this.uploadTag);
        },

        onImageUpload({ targetId }) {
            this.mediaStore.getByIdAsync(targetId).then((mediaEntity) => {
                this.element.config.media.value = mediaEntity.id;

                if (this.element.data) {
                    this.$set(this.element.data, 'mediaId', mediaEntity.id);
                    this.$set(this.element.data, 'media', mediaEntity);
                }

                this.$emit('element-update', this.element);
            });
        },

        onImageRemove() {
            this.element.config.media.value = null;

            if (this.element.data) {
                this.$set(this.element.data, 'mediaId', null);
                this.$set(this.element.data, 'media', null);
            }

            this.$emit('element-update', this.element);
        },

        onCloseModal() {
            this.mediaModalIsOpen = false;
        },

        onSelectionChanges(mediaEntity) {
            this.element.config.media.value = mediaEntity[0].id;

            if (this.element.data) {
                this.$set(this.element.data, 'mediaId', mediaEntity[0].id);
                this.$set(this.element.data, 'media', mediaEntity[0]);
            }

            this.$emit('element-update', this.element);
        },

        onOpenMediaModal() {
            this.mediaModalIsOpen = true;
        },

        onChangeMinHeight(value) {
            this.element.config.minHeight.value = value === null ? '' : value;

            this.$emit('element-update', this.element);
        }
    }
});
