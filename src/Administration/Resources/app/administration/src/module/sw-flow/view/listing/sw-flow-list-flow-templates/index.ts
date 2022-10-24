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

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// @ts-expect-error
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-flow-list-flow-templates', {
    template,

    inject: ['acl', 'repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
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
        selectedItems: Array<FlowTemplateEntity>,
        } {
        return {
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            total: 0,
            isLoading: false,
            flowTemplates: null,
            selectedItems: [],
        };
    },

    metaInfo(): MetaInfo {
        return {
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
                criteria.setTerm(this.searchTerm as string);
            }

            criteria
                // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
                .addSorting(Criteria.sort(this.sortBy, this.sortDirection))
                .addSorting(Criteria.sort('updatedAt', 'DESC'));

            return criteria;
        },

        flowTemplateColumns() {
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
        searchTerm(value): void {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
            this.onSearch(value);
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
            // @ts-expect-error
            this.isLoading = true;

            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            this.flowTemplateRepository.search(this.flowTemplateCriteria)
                .then((data: EntityCollection) => {
                    // @ts-expect-error
                    this.total = data.total;
                    // @ts-expect-error
                    this.flowTemplates = data as unknown as Array<FlowTemplateEntity>;
                })
                .finally(() => {
                    // @ts-expect-error
                    this.isLoading = false;
                });
        },

        createFlowFromTemplate(item: FlowTemplateEntity): void {
            this.$router.push({ name: 'sw.flow.create', params: { flowTemplateId: item.id } });
        },

        onEditFlow(item: FlowTemplateEntity): void {
            if (!item?.id) {
                return;
            }

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

        updateRecords(result: EntityCollection) {
            // @ts-expect-error
            this.flowTemplates = result as unknown as Array<FlowTemplateEntity>;

            // @ts-expect-error
            this.total = result.total;
        },

        selectionChange(selection: { [key:string]: FlowTemplateEntity }) {
            // @ts-expect-error
            this.selectedItems = Object.values(selection);
        },
    },
});
