import type ContentSystemLayoutDraftApiService from 'src/core/service/api/content-system-layout-draft.api.service';
import type { ContentLayoutDraft } from 'src/core/service/api/content-system-layout-draft.api.service';

import template from './sw-experience-studio-drafts-modal.html.twig';

const { Mixin } = Shopware;

type DraftRow = ContentLayoutDraft & {
    id: string;
};

type ColumnConfig = {
    property: string;
    label: string;
    allowResize: boolean;
    primary: boolean;
};

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['acl'],

    mixins: [Mixin.getByName('notification')],

    props: {
        layoutId: {
            type: String,
            required: true,
        },
        layoutName: {
            type: String,
            required: false,
            default: '',
        },
    },

    emits: ['modal-close'],

    data(): {
        drafts: ContentLayoutDraft[];
        isLoading: boolean;
        draftPendingDiscard: string | null;
    } {
        return {
            drafts: [],
            isLoading: false,
            draftPendingDiscard: null,
        };
    },

    computed: {
        rows(): DraftRow[] {
            return this.drafts.map((draft) => ({ ...draft, id: draft.versionId }));
        },

        columns(): ColumnConfig[] {
            return [
                {
                    property: 'createdAt',
                    label: this.$t('sw-experience-studio.draftsModal.columnCreatedAt'),
                    allowResize: false,
                    primary: true,
                },
                {
                    property: 'updatedAt',
                    label: this.$t('sw-experience-studio.draftsModal.columnUpdatedAt'),
                    allowResize: false,
                    primary: false,
                },
                {
                    property: 'versionId',
                    label: this.$t('sw-experience-studio.draftsModal.columnVersionId'),
                    allowResize: false,
                    primary: false,
                },
            ];
        },

        allowDiscard(): boolean {
            return this.acl.can('experience_studio.editor');
        },

        title(): string {
            return this.layoutName
                ? this.$t('sw-experience-studio.draftsModal.titleWithName', { name: this.layoutName })
                : this.$t('sw-experience-studio.draftsModal.title');
        },
    },

    created(): void {
        void this.loadDrafts();
    },

    methods: {
        draftService(): ContentSystemLayoutDraftApiService {
            return Shopware.Service('contentSystemLayoutDraftService');
        },

        async loadDrafts(): Promise<void> {
            this.isLoading = true;

            try {
                this.drafts = await this.draftService().getDrafts(this.layoutId);
            } catch {
                this.drafts = [];
                this.createNotificationError({
                    message: this.$t('sw-experience-studio.draftsModal.messageLoadError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        formatDate(value: string | null): string {
            return value ? Shopware.Utils.format.date(value) : '';
        },

        shortVersionId(versionId: string): string {
            return versionId.slice(0, 8);
        },

        onOpenDraft(versionId: string): void {
            this.$emit('modal-close');
            void this.$router.push({
                name: 'sw.experience.studio.detail',
                params: { id: this.layoutId },
                query: { versionId },
            });
        },

        onDiscardDraft(versionId: string): void {
            if (!this.allowDiscard) {
                return;
            }

            this.draftPendingDiscard = versionId;
        },

        onCancelDiscard(): void {
            this.draftPendingDiscard = null;
        },

        async onConfirmDiscard(): Promise<void> {
            const versionId = this.draftPendingDiscard;
            this.draftPendingDiscard = null;

            if (!versionId) {
                return;
            }

            this.isLoading = true;

            try {
                await this.draftService().discard(this.layoutId, versionId);
                this.createNotificationSuccess({
                    message: this.$t('sw-experience-studio.detail.messageDraftDiscarded'),
                });
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-experience-studio.detail.messageDiscardError'),
                });
            }

            await this.loadDrafts();
        },

        onClose(): void {
            this.$emit('modal-close');
        },
    },
});
