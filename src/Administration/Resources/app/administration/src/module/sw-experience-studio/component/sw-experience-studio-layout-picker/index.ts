import type Repository from 'src/core/data/repository.data';
import type CriteriaType from 'src/core/data/criteria.data';
import template from './sw-experience-studio-layout-picker.html.twig';
import './sw-experience-studio-layout-picker.scss';

const { Criteria } = Shopware.Data;

type LayoutPickerColumn = {
    property: string;
    label: string;
    primary?: boolean;
};

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory', 'acl'],

    props: {
        openLayoutIds: {
            type: Array as PropType<string[]>,
            required: false,
            default: () => [],
        },
        disabled: {
            type: Boolean,
            default: false,
        },
    },

    emits: [
        'select',
        'close',
        'delete',
    ],

    data(): {
        layouts: EntityCollection<'content_layout'> | null;
        isLoading: boolean;
        searchTerm: string;
        page: number;
        limit: number;
        total: number;
        hasLoadError: boolean;
        layoutToDelete: Pick<Entity<'content_layout'>, 'id' | 'name'> | null;
        isDeleting: boolean;
        hasDeleteError: boolean;
    } {
        return {
            layouts: null,
            isLoading: false,
            searchTerm: '',
            page: 1,
            limit: 25,
            total: 0,
            hasLoadError: false,
            layoutToDelete: null,
            isDeleting: false,
            hasDeleteError: false,
        };
    },

    computed: {
        allowDelete(): boolean {
            return this.acl.can('experience_studio.deleter');
        },

        deleteActionsDisabled(): boolean {
            return !this.allowDelete || this.disabled || this.isLoading || this.isDeleting;
        },

        layoutRepository(): Repository<'content_layout'> {
            return this.repositoryFactory.create('content_layout');
        },

        criteria(): CriteriaType {
            const criteria = new Criteria(this.page, this.limit);

            if (this.searchTerm.trim()) {
                criteria.setTerm(this.searchTerm.trim());
            }

            criteria.addSorting(Criteria.sort('updatedAt', 'DESC'));

            return criteria;
        },

        columns(): LayoutPickerColumn[] {
            return [
                {
                    property: 'name',
                    label: 'sw-experience-studio.list.columnName',
                    primary: true,
                },
                {
                    property: 'version',
                    label: 'sw-experience-studio.list.columnVersion',
                },
                {
                    property: 'updatedAt',
                    label: 'sw-experience-studio.list.columnUpdatedAt',
                },
            ];
        },
    },

    created(): void {
        void this.loadLayouts();
    },

    methods: {
        async loadLayouts(): Promise<void> {
            this.isLoading = true;
            this.hasLoadError = false;

            try {
                this.layouts = await this.layoutRepository.search(this.criteria, Shopware.Context.api);
                this.total = this.layouts.total ?? 0;
            } catch {
                this.layouts = null;
                this.hasLoadError = true;
            } finally {
                this.isLoading = false;
            }
        },

        onSearch(value: string): void {
            if (this.isDeleting) {
                return;
            }
            this.searchTerm = value;
            this.page = 1;
            void this.loadLayouts();
        },

        onPageChange({ page }: { page: number }): void {
            if (this.isDeleting) {
                return;
            }
            this.page = page;
            void this.loadLayouts();
        },

        onSelectLayout(layoutId: string): void {
            if (this.isDeleting) {
                return;
            }
            this.$emit('select', layoutId);
        },

        onClose(): void {
            if (!this.isDeleting) {
                this.$emit('close');
            }
        },

        onDeleteLayout(layout: Pick<Entity<'content_layout'>, 'id' | 'name'>): void {
            if (this.deleteActionsDisabled) {
                return;
            }
            this.layoutToDelete = layout;
            this.hasDeleteError = false;
        },

        onCancelDeleteLayout(): void {
            if (this.isDeleting) {
                return;
            }
            this.layoutToDelete = null;
            this.hasDeleteError = false;
        },

        async onConfirmDeleteLayout(): Promise<void> {
            const layout = this.layoutToDelete;
            if (!layout || this.deleteActionsDisabled) {
                return;
            }
            this.isDeleting = true;
            this.hasDeleteError = false;
            try {
                await this.layoutRepository.delete(layout.id as EntityKey<'content_layout'>, Shopware.Context.api);
                this.$emit('delete', layout.id);
                this.layoutToDelete = null;
                if (this.layouts?.length === 1 && this.page > 1) {
                    this.page -= 1;
                }
                await this.loadLayouts();
            } catch {
                this.hasDeleteError = true;
            } finally {
                this.isDeleting = false;
            }
        },
    },
});
