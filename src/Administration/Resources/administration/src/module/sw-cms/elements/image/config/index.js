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

    data() {
        return {};
    },

    computed: {
        mediaStore() {
            return State.getStore('media');
        },

        uploadStore() {
            return State.getStore('upload');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {},

        onChangeMedia({ uploadTag }) {
            this.mediaStore.sync().then(() => {
                return this.uploadStore.runUploads(uploadTag);
            });
        },

        onImageUpload(mediaEntity) {
            // this.element.config.mediaId = mediaEntity.id;
            // this.element.data.mediaId = mediaEntity.id;
            // this.element.data.media = mediaEntity;

            this.$set(this.element.config, 'mediaId', mediaEntity.id);
            this.$set(this.element.data, 'mediaId', mediaEntity.id);
            this.$set(this.element.data, 'media', mediaEntity);

            this.$emit('element-update', this.element);
        },

        onImageRemove() {
            // this.element.config.mediaId = null;
            // this.element.data.mediaId = null;
            // this.element.data.media = null;

            this.$set(this.element.config, 'mediaId', null);
            this.$set(this.element.data, 'mediaId', null);
            this.$set(this.element.data, 'media', null);

            this.$emit('element-update', this.element);
        }
    }
});
