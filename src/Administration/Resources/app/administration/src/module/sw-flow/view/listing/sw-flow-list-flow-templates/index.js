import template from './sw-flow-list-flow-templates.html.twig';
import './sw-flow-list-flow-templates.scss';

const { Component, Mixin, Data: { Criteria } } = Shopware;

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

    data() {
        return {
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            total: 0,
            isLoading: false,
            flows: null,
            currentFlow: {},
            selectedItems: [],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        flowRepository() {
            return this.repositoryFactory.create('flow');
        },

        flowCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            criteria
                .addFilter(Criteria.equals('locked', true))
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

        detailPageLinkText() {
            if (!this.acl.can('flow.editor') && this.acl.can('flow.viewer')) {
                return this.$tc('global.default.view');
            }

            return this.$tc('global.default.edit');
        },
    },

    watch: {
        searchTerm(value) {
            this.onSearch(value);
        },
    },

    created() {
        this.createComponent();
    },

    methods: {
        createComponent() {
            this.getList();
        },

        getList() {
            this.isLoading = true;

            this.flowRepository.search(this.flowCriteria)
                .then((data) => {
                    this.total = data.total;
                    this.flows = data;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        createFlowFromTemplate(item) {
            const behavior = {
                overwrites: {
                    locked: 0,
                },
            };

            this.flowRepository.clone(item.id, Shopware.Context.api, behavior)
                .then((response) => {
                    this.createNotificationSuccess({
                        message: this.$tc('sw-flow.flowNotification.messageCreateSuccess'),
                    });

                    if (response?.id) {
                        this.$router.push({ name: 'sw.flow.detail', params: { id: response.id } });
                    }
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-flow.flowNotification.messageCreateError'),
                    });
                });
        },

        onEditFlow(item) {
            if (item?.id) {
                this.$router.push({
                    name: 'sw.flow.detail',
                    params: {
                        id: item.id,
                    },
                });
            }
        },

        updateRecords(result) {
            this.flows = result;
            this.total = result.total;
        },

        getTranslatedEventName(value) {
            return value.replace(/\./g, '_');
        },

        selectionChange(selection) {
            this.selectedItems = Object.values(selection);
        },
    },
});
