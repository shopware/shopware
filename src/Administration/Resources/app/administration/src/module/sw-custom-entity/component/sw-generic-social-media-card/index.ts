import './sw-generic-social-media-card.scss';

import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import type { PropType } from 'vue';

import type Repository from 'src/core/data/repository.data';
import template from './sw-generic-social-media-card.html.twig';

const createId = Shopware.Utils.createId;

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'repositoryFactory',
    ],

    props: {
        ogTitle: {
            type: String as PropType<string | null>,
            required: false,
            default: '',
        },
        ogDescription: {
            type: String as PropType<string | null>,
            required: false,
            default: '',
        },
        ogImageId: {
            type: String as PropType<string | null>,
            required: false,
            default: null,
        },
    },

    data(): {
        ogImageEntity: Entity<'media'> | null;
        mediaModalIsOpen: boolean
        } {
        return {
            ogImageEntity: null as Entity<'media'> | null,
            mediaModalIsOpen: false,
        };
    },

    created(): void {
        void this.onCreated();
    },

    watch: {
        ogImageId: {
            handler(): void {
                void this.loadOgImage();
            },
        },
    },

    computed: {
        mediaRepository(): Repository<'media'> {
            return this.repositoryFactory.create('media');
        },

        uploadTag(): string {
            return `sw-generic-social-media-card-${createId().substring(0, 8)}`;
        },
    },

    methods: {
        onCreated(): void {
            void this.loadOgImage();
        },

        async loadOgImage(): Promise<void> {
            if (!this.ogImageId) {
                return;
            }

            // Entity is already loaded
            if (this.ogImageId === this.ogImageEntity?.id) {
                return;
            }

            this.ogImageEntity = await this.mediaRepository.get(this.ogImageId);
        },

        removeOgImage(): void {
            this.ogImageEntity = null;
            this.emitMediaId(null);
        },

        onOpenMediaModal(): void {
            this.mediaModalIsOpen = true;
        },

        onCloseMediaModal(): void {
            this.mediaModalIsOpen = false;
        },

        onImageUpload({ targetId }: { targetId: string }) {
            this.emitMediaId(targetId);
        },

        onSelectionChanges(result: Entity<'media'>[]): void {
            if (result.length !== 1) {
                this.removeOgImage();
                return;
            }

            const selection = result[0];
            this.ogImageEntity = selection;
            this.emitMediaId(selection.id);
        },

        emitMediaId(mediaId: string | null) {
            this.$emit('update:og-image-id', mediaId);
        },

        emitOgTitle(ogTitle: string) {
            this.$emit('update:og-title', ogTitle);
        },

        emitOgDescription(ogDescription: string) {
            this.$emit('update:og-description', ogDescription);
        },
    },
});
