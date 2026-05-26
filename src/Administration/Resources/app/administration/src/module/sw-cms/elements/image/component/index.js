import template from './sw-cms-el-image.html.twig';
import './sw-cms-el-image.scss';

const { Mixin, Filter } = Shopware;
const { CMS } = Shopware.Constants;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    inject: [
        'feature',
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    data() {
        return {
            mappedDemoMedia: null,
            mappedDemoMediaFetchId: 0,
        };
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        displayModeClass() {
            if (this.element.config.displayMode.value === 'standard') {
                return null;
            }

            return `is--${this.element.config.displayMode.value}`;
        },

        styles() {
            return {
                'min-height':
                    this.element.config.displayMode.value === 'cover' &&
                    this.element.config.minHeight.value &&
                    this.element.config.minHeight.value !== 0
                        ? this.element.config.minHeight.value
                        : '340px',
            };
        },

        imgStyles() {
            return {
                'align-self': this.element.config.verticalAlign.value || null,
            };
        },

        horizontalAlign() {
            return {
                'justify-content': this.element.config.horizontalAlign?.value || null,
            };
        },

        verticalAlignClass() {
            return this.element.config.verticalAlign?.value ? 'has-vertical-alignment' : null;
        },

        mediaUrl() {
            const fallBackImageFileName = CMS.MEDIA.previewMountain.slice(CMS.MEDIA.previewMountain.lastIndexOf('/') + 1);
            const staticFallBackImage = this.assetFilter(
                `administration/administration/static/img/cms/${fallBackImageFileName}`,
            );
            const elemData = this.element.data.media;
            const elemConfig = this.element.config.media;

            if (elemConfig.source === 'mapped') {
                const demoMedia = this.getDemoValue(elemConfig.value);

                if (demoMedia?.url) {
                    return demoMedia.url;
                }

                if (this.mappedDemoMedia?.url) {
                    return this.mappedDemoMedia.url;
                }

                return staticFallBackImage;
            }

            if (elemConfig.source === 'default') {
                // use only the filename
                const fileName = elemConfig.value?.slice(elemConfig.value.lastIndexOf('/') + 1) ?? '';
                return this.assetFilter(`/administration/administration/static/img/cms/${fileName}`);
            }

            if (elemData?.id) {
                return this.element.data.media.url;
            }

            if (elemData?.url) {
                return this.assetFilter(elemConfig.url);
            }

            return staticFallBackImage;
        },

        assetFilter() {
            return Filter.getByName('asset');
        },

        mediaConfigValue() {
            return this.element?.config?.media?.value;
        },
    },

    watch: {
        'cmsPageState.currentDemoEntity': {
            handler() {
                this.updateDemoValue(this.mediaConfigValue);
                this.updateMappedDemoMedia();
            },
        },

        mediaConfigValue(value) {
            this.updateDemoValue(value);
            this.updateMappedDemoMedia();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('image');
            this.initElementData('image');
            this.updateMappedDemoMedia();
        },

        updateDemoValue(value) {
            const mediaId = this.element?.data?.media?.id;
            const isSourceStatic = this.element?.config?.media?.source === 'static';

            if (isSourceStatic && mediaId && value !== mediaId) {
                this.element.config.media.value = mediaId;
            }
        },

        async updateMappedDemoMedia() {
            const fetchId = this.mappedDemoMediaFetchId + 1;
            this.mappedDemoMediaFetchId = fetchId;
            this.mappedDemoMedia = null;

            if (this.element?.config?.media?.source !== 'mapped' || !this.mediaConfigValue) {
                return;
            }

            const demoMedia = this.getDemoValue(this.mediaConfigValue);

            if (demoMedia?.url) {
                this.mappedDemoMedia = demoMedia;

                return;
            }

            if (typeof demoMedia !== 'string') {
                return;
            }

            try {
                const media = await this.mediaRepository.get(demoMedia, Shopware.Context.api);

                if (fetchId !== this.mappedDemoMediaFetchId) {
                    return;
                }

                this.mappedDemoMedia = media;
            } catch {
                if (fetchId === this.mappedDemoMediaFetchId) {
                    this.mappedDemoMedia = null;
                }
            }
        },
    },
};
