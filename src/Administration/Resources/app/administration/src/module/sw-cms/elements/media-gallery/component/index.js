import template from './sw-cms-el-media-gallery.html.twig';
import './sw-cms-el-media-gallery.scss';

const { Mixin, Filter } = Shopware;
const { CMS } = Shopware.Constants;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    data() {
        return {
            activeMedia: null,
        };
    },

    computed: {
        mediaUrls() {
            const config = this.element?.config;

            if (!config || config.source === 'default') {
                return [];
            }

            return this.element?.data?.sliderItems ?? [];
        },

        thumbnailPositionClass() {
            return `is--thumbnail-${this.element.config.thumbnailPosition.value}`;
        },

        assetFilter() {
            return Filter.getByName('asset');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('media-gallery');
            this.initElementData('media-gallery');
        },

        getPlaceholderItems() {
            const previewMountain = CMS.MEDIA.previewMountain.slice(CMS.MEDIA.previewMountain.lastIndexOf('/') + 1);
            const previewGlasses = CMS.MEDIA.previewGlasses.slice(CMS.MEDIA.previewGlasses.lastIndexOf('/') + 1);
            const previewPlant = CMS.MEDIA.previewPlant.slice(CMS.MEDIA.previewPlant.lastIndexOf('/') + 1);

            return [
                { url: this.assetFilter(`administration/administration/static/img/cms/${previewMountain}`) },
                { url: this.assetFilter(`administration/administration/static/img/cms/${previewGlasses}`) },
                { url: this.assetFilter(`administration/administration/static/img/cms/${previewPlant}`) },
            ];
        },

        onChangeActiveMedia(mediaItem, index = 0) {
            mediaItem.sliderIndex = index;
            this.activeMedia = mediaItem;
        },

        activeMediaClass(mediaItem) {
            if (!this.activeMedia) {
                return null;
            }

            return { 'is--active': mediaItem.id === this.activeMedia.id };
        },
    },
};
