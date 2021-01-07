import template from './sw-cms-el-image-gallery.html.twig';
import './sw-cms-el-image-gallery.scss';

const { Component, Mixin, Utils } = Shopware;

Component.register('sw-cms-el-image-gallery', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
    ],

    data() {
        return {
            galleryLimit: 3,
            activeMedia: null
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
            if (Utils.get(this.element, 'config.sliderItems.source') === 'mapped') {
                return this.getDemoValue(this.element.config.sliderItems.value) || [];
            }

            return Utils.get(this.element, 'data.sliderItems') || [];
        },

        isProductPage() {
            return Utils.get(this.cmsPageState, 'currentPage.type', '') === 'product_detail';
        }
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
            }
        },

        'element.config.sliderItems.value': {
            handler() {
                this.$nextTick(() => {
                    this.setGalleryLimit();
                });
            }
        }
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

            if (this.isProductPage && !this.element.data.sliderItems) {
                this.element.config.sliderItems.source = 'mapped';
                this.element.config.sliderItems.value = 'product.media';
            }
        },

        mountedComponent() {
            this.setGalleryLimit();
        },

        getPlaceholderItems() {
            return [
                { url: '/administration/static/img/cms/preview_mountain_large.jpg' },
                { url: '/administration/static/img/cms/preview_glasses_large.jpg' },
                { url: '/administration/static/img/cms/preview_plant_large.jpg' }
            ];
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
                'is--active': mediaItem.id === this.activeMedia.id
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
        }
    }
});
