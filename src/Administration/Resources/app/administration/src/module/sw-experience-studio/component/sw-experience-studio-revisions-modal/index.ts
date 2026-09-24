import type Repository from 'src/core/data/repository.data';
import type ContentSystemLayoutRevisionApiService from 'src/core/service/api/content-system-layout-revision.api.service';
import type {
    ContentLayoutBranch,
    ContentLayoutRevisionSummary,
} from 'src/core/service/api/content-system-layout-revision.api.service';

import template from './sw-experience-studio-revisions-modal.html.twig';
import './sw-experience-studio-revisions-modal.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

type ColumnConfig = {
    property: string;
    label: string;
    allowResize: boolean;
    primary: boolean;
};

const UNKNOWN_AUTHOR = '—';

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'acl',
        'repositoryFactory',
    ],

    mixins: [Mixin.getByName('notification')],

    props: {
        layoutId: {
            type: String,
            required: true,
        },
        branchId: {
            type: String,
            required: false,
            default: null,
        },
    },

    emits: [
        'modal-close',
        'published',
        'open-branch',
    ],

    data(): {
        revisions: ContentLayoutRevisionSummary[];
        branches: ContentLayoutBranch[];
        authorNames: Record<string, string>;
        isLoading: boolean;
        revisionPendingPublish: string | null;
    } {
        return {
            revisions: [],
            branches: [],
            authorNames: {},
            isLoading: false,
            revisionPendingPublish: null,
        };
    },

    computed: {
        userRepository(): Repository<'user'> {
            return this.repositoryFactory.create('user');
        },

        allowEdit(): boolean {
            return this.acl.can('experience_studio.editor');
        },

        branchNames(): Record<string, string> {
            return Object.fromEntries(
                this.branches.map((branch) => [
                    branch.id,
                    branch.name,
                ]),
            );
        },

        columns(): ColumnConfig[] {
            return [
                {
                    property: 'createdAt',
                    label: this.$t('sw-experience-studio.revisionsModal.columnCreatedAt'),
                    allowResize: false,
                    primary: true,
                },
                {
                    property: 'createdBy',
                    label: this.$t('sw-experience-studio.revisionsModal.columnAuthor'),
                    allowResize: false,
                    primary: false,
                },
                {
                    property: 'branches',
                    label: this.$t('sw-experience-studio.revisionsModal.columnStatus'),
                    allowResize: false,
                    primary: false,
                },
                {
                    property: 'id',
                    label: this.$t('sw-experience-studio.revisionsModal.columnRevision'),
                    allowResize: false,
                    primary: false,
                },
            ];
        },
    },

    created(): void {
        void this.loadHistory();
    },

    methods: {
        revisionService(): ContentSystemLayoutRevisionApiService {
            return Shopware.Service('contentSystemLayoutRevisionService');
        },

        async loadHistory(): Promise<void> {
            this.isLoading = true;

            try {
                const [
                    history,
                    branches,
                ] = await Promise.all([
                    this.revisionService().getRevisions(this.layoutId),
                    this.revisionService().getBranches(this.layoutId),
                ]);

                this.revisions = history.revisions;
                this.branches = branches;
            } catch {
                this.revisions = [];
                this.branches = [];
                this.createNotificationError({
                    message: this.$t('sw-experience-studio.revisionsModal.messageLoadError'),
                });
            } finally {
                this.isLoading = false;
            }

            await this.loadAuthorNames();
        },

        async loadAuthorNames(): Promise<void> {
            const userIds = [
                ...new Set(
                    this.revisions
                        .map((revision) => revision.createdBy)
                        .filter((userId): userId is string => userId !== null),
                ),
            ];

            if (userIds.length === 0) {
                return;
            }

            const criteria = new Criteria(1, userIds.length);
            criteria.setIds(userIds);

            try {
                const users = await this.userRepository.search(criteria, Shopware.Context.api);

                this.authorNames = Object.fromEntries(
                    users.map((user) => {
                        const fullName = `${user.firstName ?? ''} ${user.lastName ?? ''}`.trim();

                        return [
                            user.id,
                            fullName || user.username,
                        ];
                    }),
                );
            } catch {
                // Authors stay unresolved without user read access.
                this.authorNames = {};
            }
        },

        authorName(userId: string | null): string {
            return (userId && this.authorNames[userId]) || UNKNOWN_AUTHOR;
        },

        branchLabels(revision: ContentLayoutRevisionSummary): { id: string; name: string }[] {
            return revision.branches
                .filter((branchId) => this.branchNames[branchId] !== undefined)
                .map((branchId) => ({ id: branchId, name: this.branchNames[branchId] }));
        },

        formatDate(value: string): string {
            return Shopware.Utils.format.date(value);
        },

        shortRevisionId(revisionId: string): string {
            return revisionId.slice(0, 8);
        },

        onPublishRevision(revisionId: string): void {
            if (!this.allowEdit) {
                return;
            }

            this.revisionPendingPublish = revisionId;
        },

        onCancelPublish(): void {
            this.revisionPendingPublish = null;
        },

        async onConfirmPublish(): Promise<void> {
            const revisionId = this.revisionPendingPublish;
            this.revisionPendingPublish = null;

            if (!revisionId) {
                return;
            }

            this.isLoading = true;

            try {
                await this.revisionService().publish(this.layoutId, { revisionId });
                this.createNotificationSuccess({
                    message: this.$t('sw-experience-studio.revisionsModal.messagePublished'),
                });
                this.$emit('published');
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-experience-studio.revisionsModal.messagePublishError'),
                });
                await this.loadHistory();
            } finally {
                this.isLoading = false;
            }
        },

        async onOpenAsDraft(revisionId: string): Promise<void> {
            if (!this.allowEdit) {
                return;
            }

            this.isLoading = true;

            try {
                const date = Shopware.Utils.format.date(new Date().toISOString());
                const branch = await this.revisionService().createBranch(this.layoutId, {
                    fromRevisionId: revisionId,
                    name: `${this.$t('sw-experience-studio.detail.draftDefaultName')} ${date}`,
                });

                this.$emit('open-branch', branch.id);
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-experience-studio.revisionsModal.messageOpenAsDraftError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        onClose(): void {
            this.$emit('modal-close');
        },
    },
});
