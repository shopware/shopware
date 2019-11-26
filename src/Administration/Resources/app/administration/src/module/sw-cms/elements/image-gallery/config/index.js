import template from './sw-cms-el-config-image-gallery.html.twig';
import './sw-cms-el-config-image-gallery.scss';

const { Component, Mixin } = Shopware;
const { cloneDeep } = Shopware.Utils.object;
const Criteria = Shopware.Data.Criteria;

Component.register('sw-cms-el-config-image-gallery', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
    ],

    inject: ['repositoryFactory'],

    data() {
        return {
            mediaModalIsOpen: false,
            initialFolderId: null,
            enitiy: this.element,
            mediaItems: []
        };
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        uploadTag() {
            return `cms-element-media-config-${this.element.id}`;
        },

        defaultFolderName() {
            return this.cmsPageState._entityName;
        },

        sliderItems() {
            if (this.element.data && this.element.data.sliderItems && this.element.data.sliderItems.length > 0) {
                return this.element.data.sliderItems;
            }

            return [];
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('image-gallery');

            if (this.element.config.sliderItems.value.length > 0) {
                const mediaIds = [];

                this.element.config.sliderItems.value.forEach((item) => {
                    mediaIds.push(item.mediaId);
                });

                const criteria = new Criteria();
                criteria.addFilter(
                    Criteria.equalsAny('id', mediaIds)
                );

                this.mediaRepository.search(criteria, Shopware.Context.api).then((response) => {
                    this.mediaItems = response;
                });
            }
        },

        onOpenMediaModal() {
            this.mediaModalIsOpen = true;
        },

        onCloseMediaModal() {
            this.mediaModalIsOpen = false;
        },

        onImageUpload(mediaItem) {
            this.element.config.sliderItems.value.push({
                mediaUrl: mediaItem.url,
                mediaId: mediaItem.id,
                url: null,
                newTab: false
            });

            this.mediaItems.push(mediaItem);
            this.updateMediaDataValue();
            this.emitUpdateEl();
        },

        onItemRemove(mediaItem) {
            const key = mediaItem.id;
            this.element.config.sliderItems.value =
                this.element.config.sliderItems.value.filter(
                    (item) => item.mediaId !== key
                );

            this.mediaItems = this.mediaItems.filter(
                (item) => item.id !== key
            );

            this.updateMediaDataValue();
            this.emitUpdateEl();
        },

        onMediaSelectionChange(mediaItems) {
            mediaItems.forEach((item) => {
                this.element.config.sliderItems.value.push({
                    mediaUrl: item.url,
                    mediaId: item.id,
                    url: null,
                    newTab: false
                });
            });

            this.mediaItems.push(...mediaItems);
            this.updateMediaDataValue();
            this.emitUpdateEl();
        },

        updateMediaDataValue() {
            if (this.element.config.sliderItems.value) {
                const sliderItems = cloneDeep(this.element.config.sliderItems.value);

                sliderItems.forEach((galleryItem) => {
                    this.mediaItems.forEach((mediaItem) => {
                        if (galleryItem.mediaId === mediaItem.id) {
                            galleryItem.media = mediaItem;
                        }
                    });
                });

                this.$set(this.element.data, 'sliderItems', sliderItems);
            }
        },

        onChangeMinHeight(value) {
            this.element.config.minHeight.value = value === null ? '' : value;

            this.$emit('element-update', this.element);
        },

        onChangeDisplayMode(value) {
            if (['cover', 'contain'].includes(value)) {
                this.element.config.verticalAlign.value = null;
            } else {
                this.element.config.minHeight.value = '';
            }

            this.$emit('element-update', this.element);
        },

        emitUpdateEl() {
            this.$emit('element-update', this.element);
        }
    }
});
