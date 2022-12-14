import type CriteriaType from 'src/core/data/criteria.data';
import type Repository from 'src/core/data/repository.data';
import type EntityCollection from 'src/core/data/entity-collection.data';
import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import type { MetaInfo } from 'vue-meta';
import template from './sw-flow-list-flow-templates.html.twig';
import './sw-flow-list-flow-templates.scss';

interface FlowTemplateEntity extends Entity {
    name: string,
    description: string,
    eventName: string,
}

interface GridColumn {
    property: string,
    dataIndex?: string,
    label: string,
    allowResize?: boolean,
    sortable?: boolean,
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
        flowTemplates: Array<FlowTemplateEntity>|null,
        } {
        return {
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            total: 0,
            isLoading: false,
            flowTemplates: null,
        };
    },

    metaInfo(): MetaInfo {
        return {
            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
            title: this.$createTitle() as string,
        };
    },

    computed: {
        flowTemplateRepository(): Repository {
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
                },
                {
                    property: 'config.description',
                    label: this.$tc('sw-flow.list.labelColumnDescription'),
                    allowResize: false,
                    sortable: false,
                },
                {
                    property: 'createFlow',
                    label: '',
                    allowResize: false,
                    sortable: false,
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
                .then((data: EntityCollection) => {
                    this.total = data.total as number;
                    this.flowTemplates = data as unknown as Array<FlowTemplateEntity>;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        createFlowFromTemplate(item: FlowTemplateEntity): void {
            // eslint-disable-next-line @typescript-eslint/no-floating-promises
            this.$router.push({ name: 'sw.flow.create', params: { flowTemplateId: item.id } });
        },

        onEditFlow(item: FlowTemplateEntity): void {
            if (!item?.id) {
                return;
            }

            // eslint-disable-next-line @typescript-eslint/no-floating-promises
            this.$router.push({
                name: 'sw.flow.detail',
                params: {
                    id: item.id,
                },
                query: {
                    type: 'template',
                },
            });
        },
    },
});
