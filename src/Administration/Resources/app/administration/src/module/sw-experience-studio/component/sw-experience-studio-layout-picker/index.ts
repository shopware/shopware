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

    inject: ['repositoryFactory'],

    props: {
        openLayoutIds: {
            type: Array as PropType<string[]>,
            required: false,
            default: () => [],
        },
    },

    emits: [
        'select',
        'close',
    ],

    data(): {
        layouts: EntityCollection<'content_layout'> | null;
        isLoading: boolean;
        searchTerm: string;
        page: number;
        limit: number;
        total: number;
        hasLoadError: boolean;
    } {
        return {
            layouts: null,
            isLoading: false,
            searchTerm: '',
            page: 1,
            limit: 25,
            total: 0,
            hasLoadError: false,
        };
    },

    computed: {
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
            this.searchTerm = value;
            this.page = 1;
            void this.loadLayouts();
        },

        onPageChange(page: number): void {
            this.page = page;
            void this.loadLayouts();
        },

        onSelectLayout(layoutId: string): void {
            this.$emit('select', layoutId);
        },
    },
});
