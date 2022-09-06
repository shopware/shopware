import type CriteriaType from 'src/core/data/criteria.data';
import type Repository from 'src/core/data/repository.data';
import type EntityCollection from 'src/core/data/entity-collection.data';
import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import type { MetaInfo } from 'vue-meta';
import template from './sw-flow-list-flow-templates.html.twig';
import './sw-flow-list-flow-templates.scss';

interface FlowEntity extends Entity {
    name: string,
        description: string,
        eventName: string,
}

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

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
        flows: Array<FlowEntity>,
        selectedItems: Array<FlowEntity>,
        } {
        return {
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            total: 0,
            isLoading: false,
            flows: [],
            selectedItems: [],
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
        flowRepository(): Repository {
            return this.repositoryFactory.create('flow');
        },

        flowCriteria(): CriteriaType {
            const criteria = new Criteria(1, 25);

            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            criteria
                .addFilter(Criteria.equals('locked', true))
                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
                .addSorting(Criteria.sort(this.sortBy, this.sortDirection))
                .addSorting(Criteria.sort('updatedAt', 'DESC'));

            return criteria;
        },

        flowColumns() {
            return [
                {
                    property: 'name',
                    dataIndex: 'name',
                    label: this.$tc('sw-flow.list.labelColumnName'),
                    allowResize: false,
                    routerLink: 'sw.flow.detail',
                    primary: true,
                },
                {
                    property: 'description',
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
            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
            this.onSearch(value);
        },
    },

    created(): void {
        // @ts-expect-error
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call
        this.createComponent();
    },

    methods: {
        createComponent(): void {
            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
            this.getList();
        },

        getList(): void {
            // @ts-expect-error
            this.isLoading = true;

            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            this.flowRepository.search(this.flowCriteria)
                .then((data: EntityCollection) => {
                    // @ts-expect-error
                    this.total = data.total;
                    // @ts-expect-error
                    this.flows = data as unknown as Array<FlowEntity>;
                })
                .finally(() => {
                    // @ts-expect-error
                    this.isLoading = false;
                });
        },

        createFlowFromTemplate(item: FlowEntity): void {
            const behavior = {
                overwrites: {
                    locked: 0,
                },
            };

            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            this.flowRepository.clone(item.id, Shopware.Context.api, behavior)
                .then((response: FlowEntity) => {
                    // @ts-expect-error
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                    this.createNotificationSuccess({
                        message: this.$tc('sw-flow.flowNotification.messageCreateSuccess'),
                    });

                    if (response?.id) {
                        this.$router.push({ name: 'sw.flow.detail', params: { id: response.id } });
                    }
                })
                .catch(() => {
                    // @ts-expect-error
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                    this.createNotificationError({
                        message: this.$tc('sw-flow.flowNotification.messageCreateError'),
                    });
                });
        },

        onEditFlow(item: FlowEntity): void {
            if (!item?.id) {
                return;
            }

            this.$router.push({
                name: 'sw.flow.detail',
                params: {
                    id: item.id,
                },
            });
        },

        updateRecords(result: EntityCollection) {
            // @ts-expect-error
            this.flows = result as unknown as Array<FlowEntity>;
            // @ts-expect-error
            this.total = result.total;
        },

        getTranslatedEventName(value: string): string {
            return value.replace(/\./g, '_');
        },

        selectionChange(selection: { [key:string]: FlowEntity }) {
            // @ts-expect-error
            this.selectedItems = Object.values(selection);
        },
    },
});
