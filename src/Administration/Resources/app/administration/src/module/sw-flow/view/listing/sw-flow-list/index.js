import template from './sw-flow-list.html.twig';
import './sw-flow-list.scss';

const { Mixin, Data: { Criteria } } = Shopware;

/**
 * @private
 * @package business-ops
 */
export default {
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
            isDeleting: false,
            isDownloading: false,
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
                    sortable: true,
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

        onDuplicateFlow(item) {
            const behavior = {
                overwrites: {
                    name: `${item.name} - ${this.$tc('global.default.copy')}`,
                },
            };

            this.flowRepository.clone(item.id, Shopware.Context.api, behavior)
                .then((response) => {
                    this.createNotificationSuccess({
                        message: this.$tc('sw-flow.flowNotification.messageDuplicateSuccess'),
                    });

                    if (response?.id) {
                        this.$router.push({ name: 'sw.flow.detail', params: { id: response.id } });
                    }
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-flow.flowNotification.messageDuplicateError'),
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

        onDeleteFlow(item) {
            this.isDeleting = true;
            this.currentFlow = item;
        },

        onCloseDeleteModal() {
            this.isDownload = false;
            this.currentFlow = {};
        },

        onConfirmDelete(item) {
            this.isDeleting = false;
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

        updateRecords(result) {
            this.flows = result;
            this.total = result.total;

            this.$emit('on-update-total', this.total);
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
};
