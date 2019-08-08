import template from './sw-cms-el-config-image.html.twig';
import './sw-cms-el-config-image.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-config-image', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
    ],

    inject: [
        'repositoryFactory',
        'context'
    ],

    data() {
        return {
            mediaModalIsOpen: false,
            initialFolderId: null
        };
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
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

        onImageUpload({ targetId }) {
            this.mediaRepository.get(targetId, this.context).then((mediaEntity) => {
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
        },

        onChangeDisplayMode(value) {
            if (value === 'cover') {
                this.element.config.verticalAlign.value = null;
            } else {
                this.element.config.minHeight.value = '';
            }

            this.$emit('element-update', this.element);
        }
    }
});
