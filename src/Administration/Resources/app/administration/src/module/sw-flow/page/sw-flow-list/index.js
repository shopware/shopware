import template from './sw-flow-list.html.twig';

const { Component, Mixin, Data: { Criteria } } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-flow-list', {
    template,

    inject: ['acl', 'repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('listing'),
    ],

    data() {
        return {
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            total: 0,
            isDeleting: false,
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
            criteria.setTerm(this.term);
            criteria
                .addSorting(Criteria.sort(this.sortBy, this.sortDirection))
                .addSorting(Criteria.sort('updatedAt', 'DESC'));

            return criteria;
        },

        flowColumns() {
            return [
                {
                    property: 'active',
                    label: this.$tc('sw-flow.list.labelColumnActive'),
                    width: '80px',
                    sortable: false,
                },
                {
                    property: 'name',
                    dataIndex: 'name',
                    label: this.$tc('sw-flow.list.labelColumnName'),
                    allowResize: true,
                    routerLink: 'sw.flow.detail',
                    primary: true,
                },
                {
                    property: 'eventName',
                    dataIndex: 'eventName',
                    label: this.$tc('sw-flow.list.labelColumnTrigger'),
                    allowResize: true,
                    multiLine: true,
                },
                {
                    property: 'description',
                    label: this.$tc('sw-flow.list.labelColumnDescription'),
                    allowResize: true,
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

        onDeleteFlow(item) {
            this.currentFlow = item;
            this.isDeleting = true;
        },

        onCloseDeleteModal() {
            this.currentFlow = {};
        },

        onConfirmDelete(item) {
            this.currentFlow = {};

            return this.flowRepository.delete(item.id)
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc('sw-flow.flowNotification.messageDeleteSuccess'),
                    });
                    this.isDeleting = false;
                    this.getList();
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-flow.flowNotification.messageDeleteError'),
                    });
                });
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

        deleteWarningMessage() {
            return `${this.$tc('sw-flow.list.warningDeleteText')} ${this.$tc('sw-flow.list.confirmText')}`;
        },

        bulkDeleteWarningMessage(selectionCount) {
            return `${this.$tc('sw-flow.list.warningDeleteText')}
            ${this.$tc('global.entity-components.deleteMessage', selectionCount, { count: selectionCount })}`;
        },
    },
});
