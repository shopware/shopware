import template from './sw-flow-list.html.twig';

const { Component, Mixin, Data: { Criteria } } = Shopware;

Component.register('sw-flow-list', {
    template,

    inject: ['acl', 'repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            total: 0,
            isLoading: false,
            flows: null,
            currentFlow: {},
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
            const criteria = new Criteria();
            return criteria;
        },

        flowColumns() {
            return [
                {
                    property: 'active',
                    label: this.$tc('sw-flow.list.labelColumnActive'),
                    sortable: false,
                    width: '80px',
                },
                {
                    property: 'name',
                    label: this.$tc('sw-flow.list.labelColumnName'),
                    sortable: false,
                    allowResize: true,
                    routerLink: 'sw.flow.detail',
                    primary: true,
                },
                {
                    property: 'eventName',
                    label: this.$tc('sw-flow.list.labelColumnTrigger'),
                    sortable: false,
                    allowResize: true,
                },
                {
                    property: 'description',
                    label: this.$tc('sw-flow.list.labelColumnDescription'),
                    sortable: false,
                    allowResize: true,
                },
            ];
        },

        detailPageLinkText() {
            if (!this.acl.can('flow.editor') && this.acl.can('flow.viewer')) {
                return this.$tc('global.default.view');
            }

            return this.$tc('global.default.edit');
        },

        showListing() {
            return this.flows?.length;
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
                    this.getList();
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-flow.flowNotification.messageDeleteError'),
                    });
                });
        },
    },
});
