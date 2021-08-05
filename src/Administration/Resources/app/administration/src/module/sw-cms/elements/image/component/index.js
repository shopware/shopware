import template from './sw-cms-el-image.html.twig';
import './sw-cms-el-image.scss';

const { Component, Mixin, Filter } = Shopware;

Component.register('sw-cms-el-image', {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        displayModeClass() {
            if (this.element.config.displayMode.value === 'standard') {
                return null;
            }

            return `is--${this.element.config.displayMode.value}`;
        },

        styles() {
            return {
                'min-height': this.element.config.displayMode.value === 'cover' &&
                              this.element.config.minHeight.value &&
                              this.element.config.minHeight.value !== 0 ? this.element.config.minHeight.value : '340px',
                'align-self': this.element.config.verticalAlign.value || null,
            };
        },

        mediaUrl() {
            const elemData = this.element.data.media;
            const mediaSource = this.element.config.media.source;

            if (mediaSource === 'mapped') {
                const demoMedia = this.getDemoValue(this.element.config.media.value);

                if (demoMedia?.url) {
                    return demoMedia.url;
                }

                return this.assetFilter('administration/static/img/cms/preview_mountain_large.jpg');
            }

            if (elemData?.id) {
                return this.element.data.media.url;
            }

            if (elemData?.url) {
                return this.assetFilter(elemData.url);
            }

            return this.assetFilter('administration/static/img/cms/preview_mountain_large.jpg');
        },

        assetFilter() {
            return Filter.getByName('asset');
        },

        mediaConfigValue() {
            return this.element?.config?.sliderItems?.value;
        },
    },

    watch: {
        cmsPageState: {
            deep: true,
            handler() {
                this.$forceUpdate();
            },
        },

        mediaConfigValue(value) {
            const mediaId = this.element?.data?.media?.id;
            const isSourceStatic = this.element?.config?.media?.source === 'static';

            if (isSourceStatic && mediaId && value !== mediaId) {
                this.element.config.media.value = mediaId;
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('image');
            this.initElementData('image');
        },
    },
});
