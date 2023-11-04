import template from './sw-cms-el-config-image-gallery.html.twig';
import './sw-cms-el-config-image-gallery.scss';

const { Mixin } = Shopware;
const { moveItem, object: { cloneDeep } } = Shopware.Utils;
const Criteria = Shopware.Data.Criteria;

/**
 * @private
 * @package content
 */
export default {
    template,

    inject: ['repositoryFactory', 'feature'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    data() {
        return {
            mediaModalIsOpen: false,
            initialFolderId: null,
            enitiy: this.element,
            mediaItems: [],
            columnWidth: '100px',
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
        },

        sliderItemsConfigValue() {
            return this.element?.config?.sliderItems?.value;
        },

        gridAutoRows() {
            return `grid-auto-rows: ${this.columnWidth}`;
        },

        isProductPage() {
            return (this.cmsPageState?.currentPage?.type ?? '') === 'product_detail';
        },
    },

    watch: {
        sliderItems() {
            this.updateColumnWidth();
        },

        sliderItemsConfigValue(value) {
            if (!value) {
                this.element.config.sliderItems.value = [];
                return;
            }

            const isSourceMapped = this.element?.config?.sliderItems?.source === 'mapped';
            const isSliderLengthValid = value && value.length === this.sliderItems.length;

            if (isSourceMapped || isSliderLengthValid || !this.sliderItems.length) {
                return;
            }

            this.mediaItems = this.sliderItems.map((item) => {
                return item.media;
            });

            this.element.config.sliderItems.value = this.sliderItems.map(item => {
                return {
                    mediaId: item.media.id,
                    mediaUrl: item.media.url,
                    newTab: item.newTab,
                    url: item.url,
                };
            });
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        async createdComponent() {
            this.initElementConfig('image-gallery');

            const { source: sliderItemsSource, value: sliderItemsValue } = this.element.config.sliderItems;

            if (sliderItemsSource === 'static' && sliderItemsValue && sliderItemsValue.length > 0) {
                const mediaIds = sliderItemsValue.map((configElement) => {
                    return configElement.mediaId;
                });

                const criteria = new Criteria(1, 25);
                criteria.setIds(mediaIds);

                const searchResult = await this.mediaRepository.search(criteria);
                this.mediaItems = mediaIds.map((mediaId) => {
                    return searchResult.get(mediaId);
                });
            }

            this.initConfig();
        },

        mountedComponent() {
            this.updateColumnWidth();
        },

        initConfig() {
            if (!this.isProductPage
                || this.element?.translated?.config
                || this.element?.data?.sliderItems) {
                return;
            }

            this.element.config.sliderItems.source = 'mapped';
            this.element.config.sliderItems.value = 'product.media';
            this.element.config.navigationDots.value = 'inside';
            this.element.config.zoom.value = true;
            this.element.config.fullScreen.value = true;
            this.element.config.keepAspectRatioOnZoom.value = true;
            this.element.config.magnifierOverGallery.value = false;
            this.element.config.displayMode.value = 'contain';
            this.element.config.minHeight.value = '430px';
        },

        updateColumnWidth() {
            if (!this.$refs.demoMediaGrid) {
                return;
            }

            this.$nextTick(() => {
                const cssColumns = window.getComputedStyle(this.$refs.demoMediaGrid, null)
                    .getPropertyValue('grid-template-columns')
                    .split(' ');
                this.columnWidth = cssColumns[0];
            });
        },

        onOpenMediaModal() {
            this.mediaModalIsOpen = true;
        },

        onCloseMediaModal() {
            this.mediaModalIsOpen = false;
        },

        onImageUpload(mediaItem) {
            const sliderItems = this.element.config.sliderItems;
            if (sliderItems.source === 'default') {
                sliderItems.value = [];
                sliderItems.source = 'static';

                this.mediaItems = [];
            }

            sliderItems.value.push({
                mediaUrl: mediaItem.url,
                mediaId: mediaItem.id,
                url: null,
                newTab: false,
            });

            this.mediaItems.push(mediaItem);
            this.updateMediaDataValue();
            this.emitUpdateEl();
        },

        onItemRemove(mediaItem, index) {
            const key = mediaItem.id;
            this.element.config.sliderItems.value =
                this.element.config.sliderItems.value.filter(
                    (item, i) => (item.mediaId !== key || i !== index),
                );

            this.mediaItems = this.mediaItems.filter(
                (item, i) => (item.id !== key || i !== index),
            );

            this.updateMediaDataValue();
            this.emitUpdateEl();
        },

        onMediaSelectionChange(mediaItems) {
            const sliderItems = this.element.config.sliderItems;
            if (sliderItems.source === 'default') {
                sliderItems.value = [];
                sliderItems.source = 'static';

                this.mediaItems = [];
            }

            mediaItems.forEach((item) => {
                this.element.config.sliderItems.value.push({
                    mediaUrl: item.url,
                    mediaId: item.id,
                    url: null,
                    newTab: false,
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

                if (!this.element.data) {
                    this.$set(this.element, 'data', { sliderItems });
                } else {
                    this.$set(this.element.data, 'sliderItems', sliderItems);
                }
            }
        },

        onItemSort(dragData, dropData) {
            moveItem(this.mediaItems, dragData.position, dropData.position);
            moveItem(this.element.config.sliderItems.value, dragData.position, dropData.position);

            this.updateMediaDataValue();
            this.emitUpdateEl();
        },

        onChangeMinHeight(value) {
            this.element.config.minHeight.value = value === null ? '' : value;

            this.$emit('element-update', this.element);
        },

        onChangeDisplayMode(value) {
            if (['cover', 'contain'].includes(value)) {
                this.element.config.verticalAlign.value = null;
            }

            this.$emit('element-update', this.element);
        },

        emitUpdateEl() {
            this.$emit('element-update', this.element);
        },
    },
};
