import { Application, Component, Mixin } from 'src/core/shopware';
import template from './sw-cms-el-image.html.twig';
import './sw-cms-el-image.scss';

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
                              this.element.config.minHeight.value !== 0 ? this.element.config.minHeight.value : '340px'
            };
        },

        mediaUrl() {
            const initContainer = Application.getContainer('init');
            const context = initContainer.contextService;

            if (this.element.config.media.source === 'mapped') {
                const media = this.getDemoValue(this.element.config.media.value);

                if (media && media.id && media.url) {
                    return media.url;
                }
            } else if (this.element.data.media) {
                if (this.element.data.media.id) {
                    return this.element.data.media.url;
                }

                return `${context.assetsPath}${this.element.data.media.url}`;
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
        }
    }
});
