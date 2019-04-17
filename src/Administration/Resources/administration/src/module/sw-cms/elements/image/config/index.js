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
                this.$set(this.element.data, 'mediaId', mediaEntity.id);
                this.$set(this.element.data, 'media', mediaEntity);

                this.$emit('element-update', this.element);
            });
        },

        onImageRemove() {
            console.log('onImageRemove', this.element);
            this.element.config.media.value = null;
            this.$set(this.element.data, 'mediaId', null);
            this.$set(this.element.data, 'media', null);

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
        }
    }
});
