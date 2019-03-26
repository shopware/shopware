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
        },
        pageContext: {
            type: Object,
            required: true
        }
    },

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
        },

        onCloseModal() {
            this.mediaModalIsOpen = false;
        },

        onSelectionChanges(mediaEntity) {
            if (!this.element.config) {
                this.element.config = {};
            }

            this.$set(this.element.config, 'mediaId', mediaEntity[0].id);

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
