import type RepositoryType from 'src/core/data/repository.data';
import type { RuntimeSlot } from 'src/module/sw-cms/service/cms.service';
import template from './sw-cms-el-config-video.html.twig';
import './sw-cms-el-config-video.scss';

const { Component, Mixin } = Shopware;
type Options = { id: number; value: string; label: string }[];

/**
 * @private
 * @sw-package discovery
 */
export default Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory'],

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    data(): { showMediaModal: boolean; initialFolderId: string | null } {
        return {
            showMediaModal: false,
            initialFolderId: null,
        };
    },

    computed: {
        mediaRepository(): RepositoryType<'media'> {
            return this.repositoryFactory.create('media');
        },

        uploadTag(): string {
            return `cms-element-media-config-${this.element.id}`;
        },

        previewSource(): Entity<'media'> | string | null {
            const elementData = this.element.data as unknown as { media?: Entity<'media'> } | undefined;
            const mediaConfig = this.element.config.media as { value: string | null } | undefined;

            if (elementData?.media?.id) {
                return elementData.media;
            }

            return mediaConfig?.value ?? null;
        },

        displayModeOptions(): Options {
            return [
                {
                    id: 1,
                    value: 'standard',
                    label: this.$t('sw-cms.elements.general.config.label.displayModeStandard'),
                },
                {
                    id: 2,
                    value: 'stretch',
                    label: this.$t('sw-cms.elements.general.config.label.displayModeStretch'),
                },
                {
                    id: 3,
                    value: 'cover',
                    label: this.$t('sw-cms.elements.general.config.label.displayModeCover'),
                },
            ];
        },

        verticalAlignOptions(): Options {
            return [
                {
                    id: 1,
                    value: 'flex-start',
                    label: this.$t('sw-cms.elements.general.config.label.verticalAlignTop'),
                },
                {
                    id: 2,
                    value: 'center',
                    label: this.$t('sw-cms.elements.general.config.label.verticalAlignCenter'),
                },
                {
                    id: 3,
                    value: 'flex-end',
                    label: this.$t('sw-cms.elements.general.config.label.verticalAlignBottom'),
                },
            ];
        },

        horizontalAlignOptions(): Options {
            return [
                {
                    id: 1,
                    value: 'flex-start',
                    label: this.$t('sw-cms.elements.general.config.label.horizontalAlignLeft'),
                },
                {
                    id: 2,
                    value: 'center',
                    label: this.$t('sw-cms.elements.general.config.label.horizontalAlignCenter'),
                },
                {
                    id: 3,
                    value: 'flex-end',
                    label: this.$t('sw-cms.elements.general.config.label.horizontalAlignRight'),
                },
            ];
        },
    },

    watch: {
        'element.config.autoPlay.value'(autoPlay: boolean) {
            if (!autoPlay) {
                return;
            }

            let updatedProperties = false;
            const mutedConfig = this.element.config.muted as unknown as { value: boolean };
            const showCoverConfig = this.element.config.showCover as unknown as { value: boolean };

            if (!mutedConfig.value) {
                mutedConfig.value = true;
                updatedProperties = true;
            }

            if (showCoverConfig.value) {
                showCoverConfig.value = false;
                updatedProperties = true;
            }

            if (updatedProperties) {
                this.emitUpdate();
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig();
        },

        async onVideoUpload({ targetId }: { targetId: string }): Promise<void> {
            const mediaEntity = (await this.mediaRepository.get(targetId)) as Entity<'media'>;

            const mediaConfig = this.element.config.media as { value: string | null; source: string };
            mediaConfig.value = mediaEntity.id;
            mediaConfig.source = 'static';

            this.updateElementData(mediaEntity);
            this.emitUpdate();
        },

        onVideoRemove() {
            const mediaConfig = this.element.config.media as { value: string | null; source: string };
            mediaConfig.value = null;

            this.updateElementData();
            this.emitUpdate();
        },

        onCloseModal() {
            this.showMediaModal = false;
        },

        onSelectionChanges(mediaEntity: Entity<'media'>[]) {
            const media = mediaEntity[0];
            const mediaConfig = this.element.config.media as { value: string | null; source: string };
            mediaConfig.value = media.id;
            mediaConfig.source = 'static';

            this.updateElementData(media);
            this.emitUpdate();
        },

        updateElementData(media: Entity<'media'> | null = null) {
            const mediaId = media === null ? null : media.id;
            if (!this.element.data) {
                this.element.data = { mediaId, media } as unknown as RuntimeSlot['data'];

                return;
            }

            const data = this.element.data as unknown as { mediaId?: string | null; media?: Entity<'media'> | null };
            data.mediaId = mediaId;
            data.media = media;
        },

        onOpenMediaModal() {
            this.showMediaModal = true;
        },

        onChangeMinHeight(value: string | null) {
            const minHeightConfig = this.element.config.minHeight as unknown as { value: string };
            minHeightConfig.value = value === null ? '' : value;
            this.emitUpdate();
        },

        emitUpdate() {
            this.$emit('element-update', this.element);
        },
    },
});
