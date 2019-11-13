import template from './sw-cms-el-image.html.twig';
import './sw-cms-el-image.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-image', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
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
                              this.element.config.minHeight.value !== 0 ? this.element.config.minHeight.value : '340px',
                'align-self': !this.element.config.verticalAlign.value ? null : this.element.config.verticalAlign.value
            };
        },

        mediaUrl() {
            const context = Shopware.Context.api;
            const elemData = this.element.data.media;
            const mediaSource = this.element.config.media.source;

            if (mediaSource === 'mapped') {
                const demoMedia = this.getDemoValue(this.element.config.media.value);

                if (demoMedia && demoMedia.url) {
                    return demoMedia.url;
                }
            }

            if (elemData && elemData.id) {
                return this.element.data.media.url;
            }

            if (elemData && elemData.url) {
                return `${context.assetsPath}${elemData.url}`;
            }

            return `${context.assetsPath}/administration/static/img/cms/preview_mountain_large.jpg`;
        }
    },

    watch: {
        cmsPageState: {
            deep: true,
            handler() {
                this.$forceUpdate();
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('image');
            this.initElementData('image');
        }
    }
});
