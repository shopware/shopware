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

    data(): { mappedDemoMedia: Entity<'media'> | null; mappedDemoMediaFetchId: number } {
        return {
            mappedDemoMedia: null,
            mappedDemoMediaFetchId: 0,
        };
    },

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

                if (this.mappedDemoMedia?.url) {
                    return this.mappedDemoMedia.url;
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
            const elementConfig = this.element.config.media as { source: string };
            const elementData = (this.element.data as unknown as { media?: Entity<'media'> }).media;
            const media = elementConfig.source === 'mapped' ? this.mappedDemoMedia : elementData;
            const cover = media?.extensions?.videoCoverMedia as Entity<'media'> | undefined;

            return cover?.url ?? null;
        },

        assetFilter(): (value: string) => string {
            return Filter.getByName('asset');
        },

        mediaConfigValue(): string | undefined {
            return (this.element.config.media as { value?: string } | undefined)?.value;
        },
    },

    watch: {
        'cmsPageState.currentDemoEntity': {
            handler() {
                void this.updateMappedDemoMedia();
            },
        },

        mediaConfigValue() {
            void this.updateMappedDemoMedia();
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
            void this.updateMappedDemoMedia();
        },

        loadVideoCoverMedia(media?: Entity<'media'> | null) {
            const currentMedia = media ?? (this.element.data as unknown as { media?: Entity<'media'> }).media;

            if (!currentMedia || typeof currentMedia !== 'object') {
                return;
            }
            const metaData = currentMedia.metaData as { video?: { coverMediaId?: string } } | undefined;
            const coverMediaId = metaData?.video?.coverMediaId;
            if (!coverMediaId) {
                return;
            }

            const existingCover = currentMedia.extensions?.videoCoverMedia as Entity<'media'> | undefined;
            if (existingCover?.id === coverMediaId) {
                return;
            }

            void this.mediaRepository.get(coverMediaId, Shopware.Context.api).then((cover: Entity<'media'> | null) => {
                if (!cover) {
                    return;
                }

                currentMedia.extensions = {
                    ...(currentMedia.extensions ?? {}),
                    videoCoverMedia: cover,
                };
            });
        },

        async updateMappedDemoMedia() {
            const fetchId = this.mappedDemoMediaFetchId + 1;
            this.mappedDemoMediaFetchId = fetchId;
            this.mappedDemoMedia = null;

            const elementConfig = this.element.config.media as { source: string; value?: string };

            if (elementConfig.source !== 'mapped' || !elementConfig.value) {
                return;
            }

            const demoMedia = this.getDemoValue(elementConfig.value);

            if (demoMedia && typeof demoMedia === 'object' && 'url' in demoMedia) {
                this.mappedDemoMedia = demoMedia as Entity<'media'>;
                this.loadVideoCoverMedia(this.mappedDemoMedia);

                return;
            }

            if (typeof demoMedia !== 'string') {
                return;
            }

            try {
                const media = await this.mediaRepository.get(demoMedia, Shopware.Context.api);

                if (fetchId !== this.mappedDemoMediaFetchId || !media) {
                    return;
                }

                this.mappedDemoMedia = media;
                this.loadVideoCoverMedia(media);
            } catch {
                if (fetchId === this.mappedDemoMediaFetchId) {
                    this.mappedDemoMedia = null;
                }
            }
        },
    },
});
