import { Component, State } from 'src/core/shopware';
import template from './sw-cms-el-config-image.html.twig';
import './sw-cms-el-config-image.scss';

Component.register('sw-cms-el-config-image', {
    template,

    model: {
        prop: 'element',
        event: 'element-update'
    },

    props: {
        element: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        }
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

    methods: {
        onChangeMedia() {
            return this.uploadStore.runUploads(this.uploadTag);
        },

        onImageUpload({ targetId }) {
            this.mediaStore.getByIdAsync(targetId).then((mediaEntity) => {
                this.$set(this.element.config, 'mediaId', mediaEntity.id);
                this.$set(this.element.data, 'mediaId', mediaEntity.id);
                this.$set(this.element.data, 'media', mediaEntity);

                this.$emit('element-update', this.element);
            });
        },

        onImageRemove() {
            this.$set(this.element.config, 'mediaId', null);
            this.$set(this.element.data, 'mediaId', null);
            this.$set(this.element.data, 'media', null);

            this.$emit('element-update', this.element);
        }
    }
});
