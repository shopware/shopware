import CMS from '../../../constant/sw-cms.constant';
import template from './sw-cms-el-image-gallery.html.twig';
import './sw-cms-el-image-gallery.scss';

const { Mixin, Filter } = Shopware;

/**
 * @private
 * @package content
 */
export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    data() {
        return {
            galleryLimit: 3,
            activeMedia: null,
        };
    },

    computed: {
        currentDeviceView() {
            return this.cmsPageState.currentCmsDeviceView;
        },

        galleryPositionClass() {
            return `is--preview-${this.element.config.galleryPosition.value}`;
        },

        currentDeviceViewClass() {
            if (this.currentDeviceView) {
                return `is--${this.currentDeviceView}`;
            }

            return null;
        },

        verticalAlignStyle() {
            if (!this.element.config.verticalAlign.value) {
                return null;
            }

            return `align-content: ${this.element.config.verticalAlign.value};`;
        },

        mediaUrls() {
            const config = this.element?.config;

            if (!config || config.source === 'default') {
                return [];
            }

            if (config.source === 'mapped') {
                return this.getDemoValue(config.sliderItems.value) || [];
            }

            return this.element?.data?.sliderItems || [];
        },

        isProductPage() {
            return (this.cmsPageState?.currentPage?.type ?? '') === 'product_detail';
        },

        assetFilter() {
            return Filter.getByName('asset');
        },
    },

    watch: {
        currentDeviceView() {
            if (this.currentDeviceView === 'mobile') {
                this.galleryLimit = 0;
            }

            // timeout due to css transition 0.4s
            setTimeout(() => {
                this.setGalleryLimit();
            }, 400);
        },

        'element.config.galleryPosition.value': {
            deep: true,
            handler() {
                this.$nextTick(() => {
                    this.setGalleryLimit();
                });
            },
        },

        'element.config.sliderItems.value': {
            handler(value) {
                if (!value) {
                    this.element.config.sliderItems.value = [];
                    return;
                }

                this.$nextTick(() => {
                    this.setGalleryLimit();
                });
            },
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('image-gallery');
            this.initElementData('image-gallery');

            if (!this.isProductPage
                || this.element?.translated?.config?.sliderItems
                || this.element?.data?.sliderItems) {
                return;
            }

            this.element.config.sliderItems.source = 'mapped';
            this.element.config.sliderItems.value = 'product.media';
            this.element.config.navigationDots.value = 'inside';
            this.element.config.zoom.value = true;
            this.element.config.fullScreen.value = true;
            this.element.config.displayMode.value = 'contain';
            this.element.config.minHeight.value = '430px';
        },

        mountedComponent() {
            this.setGalleryLimit();
        },

        getPlaceholderItems() {
            if (this.element?.config?.source !== 'default') {
                const previewMountain = CMS.MEDIA.previewMountain.slice(CMS.MEDIA.previewMountain.lastIndexOf('/') + 1);
                const previewGlasses = CMS.MEDIA.previewGlasses.slice(CMS.MEDIA.previewGlasses.lastIndexOf('/') + 1);
                const previewPlant = CMS.MEDIA.previewPlant.slice(CMS.MEDIA.previewPlant.lastIndexOf('/') + 1);

                return [
                    { url: this.assetFilter(`administration/static/img/cms/${previewMountain}`) },
                    { url: this.assetFilter(`administration/static/img/cms/${previewGlasses}`) },
                    { url: this.assetFilter(`administration/static/img/cms/${previewPlant}`) },
                ];
            }

            return this.element.config.sliderItems.value.map(media => {
                const fileName = media.fileName.slice(media.fileName.lastIndexOf('/') + 1);

                return { url: this.assetFilter(`/administration/static/img/cms/${fileName}`) };
            });
        },

        onChangeGalleryImage(mediaItem, index = 0) {
            mediaItem.sliderIndex = index;
            this.activeMedia = mediaItem;
        },

        activeMediaClass(mediaItem) {
            if (!this.activeMedia) {
                return null;
            }

            return {
                'is--active': mediaItem.id === this.activeMedia.id,
            };
        },

        setGalleryLimit() {
            if (this.element.config.sliderItems.value.length === 0) {
                return;
            }

            let boxSpace = 0;
            let elSpace = 0;
            const elGap = 8;
            const arrowAndGapWidth = 36;

            if (this.element.config.galleryPosition.value === 'underneath') {
                boxSpace = this.$refs.galleryItemHolder.offsetWidth - arrowAndGapWidth;
                elSpace = 92;
            } else {
                boxSpace = this.$refs.galleryItemHolder.offsetHeight;
                elSpace = 64;
            }

            this.galleryLimit = Math.floor(boxSpace / (elSpace + elGap));
        },
    },
};
