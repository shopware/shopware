import type EntityCollection from '@shopware-ag/admin-extension-sdk/es/data/_internals/EntityCollection';
import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import type { MetaInfo } from 'vue-meta';
import type Repository from '../../../../../core/data/repository.data';
import type CriteriaType from '../../../../../core/data/criteria.data';
import template from './sw-flow-list-flow-templates.html.twig';
import './sw-flow-list-flow-templates.scss';

interface GridColumn {
    property: string,
    dataIndex?: string,
    label: string,
    allowResize?: boolean,
    sortable?: boolean,
    align: string,
}

const { Mixin, Data: { Criteria } } = Shopware;

/**
 * @private
 * @package business-ops
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['acl', 'repositoryFactory'],

    mixins: [
        Mixin.getByName('listing'),
    ],

    props: {
        searchTerm: {
            type: String,
            required: false,
            default: '',
        },
    },

    data(): {
        sortBy: string,
        sortDirection: string,
        total: number,
        isLoading: boolean,
        flowTemplates: EntityCollection<'flow_template'>|[],
        } {
        return {
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            total: 0,
            isLoading: false,
            flowTemplates: [],
        };
    },

    metaInfo(): MetaInfo {
        return {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
            title: this.$createTitle(),
        };
    },

    computed: {
        flowTemplateRepository(): Repository<'flow_template'> {
            return this.repositoryFactory.create('flow_template');
        },

        flowTemplateCriteria(): CriteriaType {
            const criteria = new Criteria(1, 25);

            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            // @ts-expect-error - Mixin methods are not recognized
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection))
                .addSorting(Criteria.sort('updatedAt', 'DESC'));

            return criteria;
        },

        flowTemplateColumns(): GridColumn[] {
            return [
                {
                    property: 'name',
                    dataIndex: 'name',
                    label: this.$tc('sw-flow.list.labelColumnName'),
                    allowResize: false,
                    align: 'left',
                },
                {
                    property: 'config.description',
                    label: this.$tc('sw-flow.list.labelColumnDescription'),
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
    },

    watch: {
        searchTerm: {
            immediate: true,
            handler(value): void {
                // @ts-expect-error - Mixin methods are not recognized
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                this.onSearch(value);
            },
        },
    },

    created(): void {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call
        this.createComponent();
    },

    methods: {
        createComponent(): void {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
            this.getList();
        },

        getList(): void {
            this.isLoading = true;

            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            this.flowTemplateRepository.search(this.flowTemplateCriteria)
                .then((data: EntityCollection<'flow_template'>) => {
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                    this.total = data.total as number;
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
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
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                params: { id: item.id },
                query: { type: 'template' },
            });
        },
    },
});
