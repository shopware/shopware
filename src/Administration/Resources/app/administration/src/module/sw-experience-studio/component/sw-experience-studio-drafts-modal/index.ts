import type ContentSystemLayoutRevisionApiService from 'src/core/service/api/content-system-layout-revision.api.service';
import type { ContentLayoutBranch } from 'src/core/service/api/content-system-layout-revision.api.service';

import template from './sw-experience-studio-drafts-modal.html.twig';

const { Mixin } = Shopware;

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
        branches: ContentLayoutBranch[];
        isLoading: boolean;
        branchPendingDelete: string | null;
    } {
        return {
            branches: [],
            isLoading: false,
            branchPendingDelete: null,
        };
    },

    computed: {
        columns(): ColumnConfig[] {
            return [
                {
                    property: 'name',
                    label: this.$t('sw-experience-studio.draftsModal.columnName'),
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
                    property: 'base',
                    label: this.$t('sw-experience-studio.draftsModal.columnBase'),
                    allowResize: false,
                    primary: false,
                },
            ];
        },

        allowDelete(): boolean {
            return this.acl.can('experience_studio.editor');
        },

        title(): string {
            return this.layoutName
                ? this.$t('sw-experience-studio.draftsModal.titleWithName', { name: this.layoutName })
                : this.$t('sw-experience-studio.draftsModal.title');
        },
    },

    created(): void {
        void this.loadBranches();
    },

    methods: {
        revisionService(): ContentSystemLayoutRevisionApiService {
            return Shopware.Service('contentSystemLayoutRevisionService');
        },

        async loadBranches(): Promise<void> {
            this.isLoading = true;

            try {
                this.branches = await this.revisionService().getBranches(this.layoutId);
            } catch {
                this.branches = [];
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

        shortRevisionId(revisionId: string): string {
            return revisionId.slice(0, 8);
        },

        onOpenBranch(branchId: string): void {
            this.$emit('modal-close');
            void this.$router.push({
                name: 'sw.experience.studio.detail',
                params: { id: this.layoutId },
                query: { branch: branchId },
            });
        },

        onDeleteBranch(branchId: string): void {
            if (!this.allowDelete) {
                return;
            }

            this.branchPendingDelete = branchId;
        },

        onCancelDelete(): void {
            this.branchPendingDelete = null;
        },

        async onConfirmDelete(): Promise<void> {
            const branchId = this.branchPendingDelete;
            this.branchPendingDelete = null;

            if (!branchId) {
                return;
            }

            this.isLoading = true;

            try {
                await this.revisionService().deleteBranch(this.layoutId, branchId);
                this.createNotificationSuccess({
                    message: this.$t('sw-experience-studio.draftsModal.messageDeleted'),
                });
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-experience-studio.draftsModal.messageDeleteError'),
                });
            }

            await this.loadBranches();
        },

        onClose(): void {
            this.$emit('modal-close');
        },
    },
});
