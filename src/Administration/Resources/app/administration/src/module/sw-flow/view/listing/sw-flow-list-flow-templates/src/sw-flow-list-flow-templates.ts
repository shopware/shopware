import template from './sw-flow-list-flow-templates.html.twig';
import './sw-flow-list-flow-templates.scss';
import {
    aclService,
    type CriteriaType,
    getAssetFilter,
    type Repository,
    repositoryFactoryService,
    wrapComponentConfig,
} from '../dependencies';

interface GridColumn {
    property: string;
    dataIndex?: string;
    label: string;
    allowResize?: boolean;
    sortable?: boolean;
    align: string;
}

const {
    Mixin,
    Data: { Criteria },
} = Shopware;

/**
 * @private
 * @sw-package after-sales
 */
export default wrapComponentConfig({
    template,

    inject: [aclService, repositoryFactoryService],

    mixins: [Mixin.getByName('listing')],

    props: {
        searchTerm: {
            type: String,
            required: false,
            default: '',
        },
    },

    data(): {
        sortBy: string;
        sortDirection: string;
        total: number;
        isLoading: boolean;
        flowTemplates: EntityCollection<'flow_template'> | [];
    } {
        return {
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            total: 0,
            isLoading: false,
            flowTemplates: [],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        flowTemplateRepository(): Repository<'flow_template'> {
            return this.repositoryFactory.create('flow_template');
        },

        flowTemplateCriteria(): CriteriaType {
            const criteria = new Criteria(1, 25);

            if (this.term) {
                criteria.setTerm(this.term);
            }

            criteria
                // @ts-expect-error - Mixin methods are not recognized
                .addSorting(Criteria.sort(this.sortBy, this.sortDirection))
                .addSorting(Criteria.sort('updatedAt', 'DESC'));

            return criteria;
        },

        flowTemplateColumns(): GridColumn[] {
            return [
                {
                    property: 'name',
                    dataIndex: 'name',
                    label: this.$t('sw-flow.list.labelColumnName'),
                    allowResize: false,
                    align: 'left',
                },
                {
                    property: 'config.description',
                    label: this.$t('sw-flow.list.labelColumnDescription'),
                    allowResize: false,
                    sortable: false,
                    align: 'left',
                },
                {
                    property: 'createFlow',
                    label: '',
                    allowResize: false,
                    sortable: false,
                    align: 'right',
                },
            ];
        },

        assetFilter() {
            return getAssetFilter();
        },
    },

    watch: {
        searchTerm: {
            immediate: true,
            handler(value: string): void {
                this.onSearch(value);
            },
        },
    },

    created(): void {
        this.createComponent();
    },

    methods: {
        createComponent(): void {
            this.getList();
        },

        getList(): void {
            this.isLoading = true;

            void this.flowTemplateRepository
                .search(this.flowTemplateCriteria)
                .then((data: EntityCollection<'flow_template'>) => {
                    this.total = data.total as number;
                    this.flowTemplates = data;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onEditFlow(item: Entity<'flow_template'>): void {
            if (!item?.id) {
                return;
            }

            // eslint-disable-next-line @typescript-eslint/no-floating-promises
            this.$router.push({
                name: 'sw.flow.detail',
                params: { id: item.id },
                query: { type: 'template' },
            });
        },
    },
});
