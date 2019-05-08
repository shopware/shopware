import { Component, Mixin, State } from 'src/core/shopware';
import { cloneDeep } from '../../../../../core/service/utils/object.utils';
import template from './sw-cms-el-config-image-slider.html.twig';
import './sw-cms-el-config-image-slider.scss';

Component.register('sw-cms-el-config-image-slider', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
    ],

    data() {
        return {
            mediaModalIsOpen: false,
            initialFolderId: null,
            entity: this.element,
            mediaItems: []
        };
    },

    computed: {
        uploadTag() {
            return `cms-element-media-config-${this.element.id}`;
        },

        mediaStore() {
            return State.getStore('media');
        },

        pageStore() {
            return State.getStore('cms_page');
        },

        items() {
            if (this.element.config && this.element.config.sliderItems && this.element.config.sliderItems.value) {
                return this.element.config.sliderItems.value;
            }

            return [];
        },

        defaultFolderName() {
            return this.pageStore._entityName;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('image-slider');

            if (this.element.config.sliderItems.value.length > 0) {
                const mediaIds = [];
                this.element.config.sliderItems.value.forEach((item) => {
                    mediaIds.push(item.mediaId);
                });

                this.mediaStore.getList({
                    ids: mediaIds
                }).then((response) => {
                    this.mediaItems = response.items;
                });
            }
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
            this.element.config.sliderItems.value = this.element.config.sliderItems.value.filter(
                (item) => item.mediaId !== key
            );

            this.mediaItems = this.mediaItems.filter(
                (item) => item.id !== key
            );

            this.updateMediaDataValue();
            this.emitUpdateEl();
        },

        onCloseMediaModal() {
            this.mediaModalIsOpen = false;
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
            if (this.element.data && this.element.config.sliderItems.value) {
                const sliderItems = cloneDeep(this.element.config.sliderItems.value);

                sliderItems.forEach((sliderItem) => {
                    this.mediaItems.forEach((mediaItem) => {
                        if (sliderItem.mediaId === mediaItem.id) {
                            sliderItem.media = mediaItem;
                        }
                    });
                });

                this.$set(this.element.data, 'sliderItems', sliderItems);
            }
        },

        onOpenMediaModal() {
            this.mediaModalIsOpen = true;
        },


        onNavigationChange() {
            this.$set(this.element.data, 'navigation', this.element.config.navigation.value);

            this.emitUpdateEl();
        },

        onChangeMinHeight(value) {
            this.element.config.minHeight.value = value === null ? '' : value;

            this.$emit('element-update', this.element);
        },

        emitUpdateEl() {
            if (!this.element.data.navigation) {
                this.$set(this.element.data, 'navigation', this.element.config.navigation.value);
            }

            this.$emit('element-update', this.element);
        }
    }
});
