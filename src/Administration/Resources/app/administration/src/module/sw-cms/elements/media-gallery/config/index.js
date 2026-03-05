import template from './sw-cms-el-config-media-gallery.html.twig';
import './sw-cms-el-config-media-gallery.scss';

const { Mixin } = Shopware;
const {
    moveItem,
    object: { cloneDeep },
} = Shopware.Utils;
const Criteria = Shopware.Data.Criteria;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    inject: [
        'repositoryFactory',
    ],

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    data() {
        return {
            mediaModalIsOpen: false,
            entity: this.element,
            mediaItems: [],
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
            if (this.element.data?.sliderItems?.length > 0) {
                return this.element.data.sliderItems;
            }

            return [];
        },

        sliderItemsConfigValue() {
            return this.element?.config?.sliderItems?.value;
        },

        isFixedHeight() {
            return this.element?.config?.displayMode?.value === 'fixedHeight';
        },

        displayModeOptions() {
            return [
                { value: 'auto', label: this.$tc('sw-cms.elements.mediaGallery.config.label.displayModeAuto') },
                {
                    value: 'fixedHeight',
                    label: this.$tc('sw-cms.elements.mediaGallery.config.label.displayModeFixedHeight'),
                },
            ];
        },

        thumbnailPositionOptions() {
            return [
                { value: 'left', label: this.$tc('sw-cms.elements.mediaGallery.config.label.thumbnailPositionLeft') },
                { value: 'bottom', label: this.$tc('sw-cms.elements.mediaGallery.config.label.thumbnailPositionBottom') },
            ];
        },
    },

    watch: {
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

            this.mediaItems = this.sliderItems.map((item) => item.media);

            this.element.config.sliderItems.value = this.sliderItems.map((item) => ({
                mediaId: item.media.id,
                mediaUrl: item.media.url,
                newTab: item.newTab,
                url: item.url,
            }));
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.initElementConfig('media-gallery');
            await this.initGalleryItems();
        },

        async initGalleryItems() {
            const { source: sliderItemsSource, value: sliderItemsValue } = this.element.config.sliderItems;

            if (sliderItemsSource === 'static' && sliderItemsValue?.length > 0) {
                const mediaIds = sliderItemsValue.map((item) => item.mediaId);

                const criteria = new Criteria(1, 25);
                criteria.setIds(mediaIds);

                const searchResult = await this.mediaRepository.search(criteria);
                this.mediaItems = mediaIds.map((mediaId) => searchResult.get(mediaId));
            }
        },

        onOpenMediaModal() {
            this.mediaModalIsOpen = true;
        },

        onCloseMediaModal() {
            this.mediaModalIsOpen = false;
        },

        async onImageUpload(mediaItem) {
            const resolvedMediaItem = await this.getMediaItem(mediaItem);

            if (!resolvedMediaItem) {
                return;
            }

            const sliderItems = this.element.config.sliderItems;
            if (sliderItems.source === 'default') {
                sliderItems.value = [];
                sliderItems.source = 'static';
                this.mediaItems = [];
            }

            const alreadyExists = this.mediaItems.find((item) => item.id === resolvedMediaItem.id);
            if (alreadyExists) {
                this.mediaItems = this.mediaItems.filter((item) => item.id !== resolvedMediaItem.id);
                sliderItems.value = sliderItems.value.filter((item) => item.mediaId !== resolvedMediaItem.id);
            }

            sliderItems.value.push({
                mediaUrl: resolvedMediaItem.url,
                mediaId: resolvedMediaItem.id,
                url: null,
                newTab: false,
            });

            this.mediaItems.push(resolvedMediaItem);
            this.updateMediaDataValue();
            this.emitUpdateEl();
        },

        async getMediaItem(mediaItem) {
            if (!mediaItem?.targetId) {
                return mediaItem;
            }

            return this.mediaRepository.get(mediaItem.targetId);
        },

        onItemRemove(mediaItem, index) {
            const key = mediaItem.id;
            this.element.config.sliderItems.value = this.element.config.sliderItems.value.filter(
                (item, i) => item.mediaId !== key || i !== index,
            );

            this.mediaItems = this.mediaItems.filter((item, i) => item.id !== key || i !== index);

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
                sliderItems.value.push({
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
                    this.element.data = { sliderItems };
                } else {
                    this.element.data.sliderItems = sliderItems;
                }
            }
        },

        onItemSort(dragData, dropData) {
            moveItem(this.mediaItems, dragData.position, dropData.position);
            moveItem(this.element.config.sliderItems.value, dragData.position, dropData.position);

            this.updateMediaDataValue();
            this.emitUpdateEl();
        },

        emitUpdateEl() {
            this.$emit('element-update', this.element);
        },
    },
};
