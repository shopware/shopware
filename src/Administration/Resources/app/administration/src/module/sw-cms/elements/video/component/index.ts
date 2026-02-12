import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-cms-el-video.html.twig';
import './sw-cms-el-video.scss';

const { Component, Mixin, Filter } = Shopware;

/**
 * @private
 * @sw-package discovery
 */
export default Component.wrapComponentConfig({
    template,

    inject: [
        'feature',
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        mediaRepository(): RepositoryType<'media'> {
            return this.repositoryFactory.create('media');
        },

        styles(): { 'min-height': string } {
            return {
                'min-height':
                    this.element.config.displayMode.value === 'cover' && this.element.config.minHeight.value !== '0'
                        ? this.element.config.minHeight.value
                        : '340px',
            };
        },

        wrapperStyles(): { 'justify-content': string | null; 'align-items': string | null } {
            return {
                'justify-content': this.element.config.horizontalAlign?.value || null,
                'align-items': this.element.config.verticalAlign?.value || null,
            };
        },

        placeholderStyles(): { 'background-image': string } {
            const url = this.assetFilter('/administration/administration/static/img/cms/preview_mountain_large.webp');

            return {
                'background-image': `url(${url})`,
            };
        },

        contentClasses(): Record<string, boolean> {
            return {
                'has--placeholder': !this.mediaUrl,
                'is--stretch': this.element.config.displayMode.value === 'stretch',
                'is--cover': this.element.config.displayMode.value === 'cover',
            };
        },

        mediaUrl(): string | null {
            const elementData = (this.element.data as unknown as { media?: Entity<'media'> }).media;
            const elementConfig = this.element.config.media as { source: string; value?: string };

            if (elementConfig.source === 'mapped') {
                const mappedValue = elementConfig.value ?? '';
                const demoMedia = this.getDemoValue(mappedValue) as { url?: string } | null;

                if (demoMedia?.url) {
                    return demoMedia.url;
                }

                return null;
            }

            if (elementConfig.source === 'default') {
                const fileName = elementConfig.value?.slice(elementConfig.value.lastIndexOf('/') + 1) ?? '';

                return this.assetFilter(`/administration/administration/static/img/cms/${fileName}`);
            }

            if (elementData?.url) {
                return elementData.url ?? null;
            }

            return null;
        },

        coverUrl(): string | null {
            const elementData = (this.element.data as unknown as { media?: Entity<'media'> }).media;
            const cover = elementData?.extensions?.videoCoverMedia as Entity<'media'> | undefined;

            return cover?.url ?? null;
        },

        assetFilter(): (value: string) => string {
            return Filter.getByName('asset');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig();
            this.initElementData('video');
            this.loadVideoCoverMedia();
        },

        loadVideoCoverMedia() {
            const media = (this.element?.data as unknown as { media?: Entity<'media'> }).media;
            if (!media || typeof media !== 'object') {
                return;
            }
            const metaData = media?.metaData as { video?: { coverMediaId?: string } } | undefined;
            const coverMediaId = metaData?.video?.coverMediaId;
            if (!coverMediaId) {
                return;
            }

            const existingCover = media.extensions?.videoCoverMedia as Entity<'media'> | undefined;
            if (existingCover?.id === coverMediaId) {
                return;
            }

            void this.mediaRepository.get(coverMediaId, Shopware.Context.api).then((cover: Entity<'media'> | null) => {
                if (!cover) {
                    return;
                }

                media.extensions = {
                    ...(media.extensions ?? {}),
                    videoCoverMedia: cover,
                };
            });
        },
    },
});
