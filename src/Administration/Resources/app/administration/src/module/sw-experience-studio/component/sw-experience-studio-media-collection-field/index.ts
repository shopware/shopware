import template from './sw-experience-studio-media-collection-field.html.twig';
import './sw-experience-studio-media-collection-field.scss';

const { Utils } = Shopware;

type MediaListItem = {
    mediaId: string;
    url: string;
    position: number;
};

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    emits: ['update:value'],

    props: {
        value: {
            type: Array as PropType<string[] | null>,
            required: false,
            default: null,
        },
        label: {
            type: String,
            required: false,
            default: null,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data(): { showMediaModal: boolean; uploadTag: string } {
        return {
            showMediaModal: false,
            uploadTag: Utils.createId(),
        };
    },

    computed: {
        mediaIds(): string[] {
            return Array.isArray(this.value) ? this.value : [];
        },

        // Lightweight host object expected by sw-media-list-selection-v2
        // (only id / isLoading / getEntityName are read).
        listEntity(): { id: string; isLoading: boolean; getEntityName: () => string } {
            return {
                id: this.uploadTag,
                isLoading: false,
                getEntityName: () => 'media',
            };
        },

        // sw-media-list-selection-item-v2 renders the preview from `mediaId`,
        // so no hydrated media entity is required here.
        entityMediaItems(): MediaListItem[] {
            return this.mediaIds.map((mediaId, position) => ({
                mediaId,
                url: mediaId,
                position,
            }));
        },
    },

    methods: {
        onOpenMediaModal(): void {
            if (this.disabled) {
                return;
            }

            this.showMediaModal = true;
        },

        onCloseMediaModal(): void {
            this.showMediaModal = false;
        },

        onMediaSelectionChange(selection: Array<{ id: string }>): void {
            const merged = [...this.mediaIds];

            selection.forEach((media) => {
                if (!merged.includes(media.id)) {
                    merged.push(media.id);
                }
            });

            this.showMediaModal = false;
            this.emitValue(merged);
        },

        onUploadFinish({ targetId }: { targetId: string }): void {
            if (this.mediaIds.includes(targetId)) {
                return;
            }

            this.emitValue([...this.mediaIds, targetId]);
        },

        onItemRemove(mediaItem: MediaListItem): void {
            this.emitValue(this.mediaIds.filter((id) => id !== mediaItem.mediaId));
        },

        onItemSort(dragData: MediaListItem, dropData: MediaListItem): void {
            if (dragData.position === dropData.position) {
                return;
            }

            const ids = [...this.mediaIds];
            const [moved] = ids.splice(dragData.position, 1);
            ids.splice(dropData.position, 0, moved);

            this.emitValue(ids);
        },

        emitValue(ids: string[]): void {
            this.$emit('update:value', ids.length > 0 ? ids : null);
        },
    },
});
