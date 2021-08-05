import template from './sw-mail-header-footer-list.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-mail-header-footer-list', {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
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
            mailHeaderFooters: null,
            showDeleteModal: null,
            isLoading: false,
        };
    },

    computed: {
        mailHeaderFooterRepository() {
            return this.repositoryFactory.create('mail_header_footer');
        },

        skeletonItemAmount() {
            return this.mailHeaderFooters && this.mailHeaderFooters.length !== 0 ? this.mailHeaderFooters.length : 3;
        },

        showListing() {
            return !!this.mailHeaderFooters && this.mailHeaderFooters.length !== 0;
        },
    },

    watch: {
        searchTerm() {
            this.getList();
        },
    },

    methods: {
        onEdit(mailHeaderFooter) {
            if (mailHeaderFooter?.id) {
                this.$router.push({
                    name: 'sw.mail.template.detail_head_foot',
                    params: {
                        id: mailHeaderFooter.id,
                    },
                });
            }
        },

        getList() {
            this.isLoading = true;

            const criteria = new Criteria(this.page, this.limit);
            criteria.addAssociation('salesChannels');

            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            this.mailHeaderFooterRepository.search(criteria).then((items) => {
                this.total = items.total;
                this.mailHeaderFooters = items;
                this.isLoading = false;

                return this.mailHeaderFooters;
            });
        },

        getListColumns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                label: 'sw-mail-header-footer.list.columnName',
                allowResize: true,
                primary: true,
            }, {
                property: 'description',
                dataIndex: 'description',
                label: 'sw-mail-header-footer.list.columnDescription',
                allowResize: true,
            }, {
                property: 'salesChannels.name',
                dataIndex: 'salesChannels.name',
                label: 'sw-mail-header-footer.list.columnSalesChannels',
                allowResize: true,
                sortable: false,
            }];
        },

        getSalesChannelsString(item) {
            if (typeof item.salesChannels === 'undefined') {
                return '';
            }
            let salesChannels = '';

            item.salesChannels.forEach((salesChannel) => {
                if (salesChannels !== '') {
                    salesChannels += ', ';
                }
                salesChannels += `${salesChannel.translated.name}`;
            });

            if (item.salesChannels.length >= 5) {
                salesChannels += '...';
            }

            return salesChannels;
        },

        onDuplicate(id) {
            this.mailHeaderFooterRepository.clone(id).then((mailHeaderFooter) => {
                this.$router.push(
                    {
                        name: 'sw.mail.template.detail_head_foot',
                        params: { id: mailHeaderFooter.id },
                    },
                );
            });
        },

        checkCanBeDeleted(mailHeaderFooter) {
            return !mailHeaderFooter.salesChannels.length;
        },

        onDelete(item) {
            this.$refs.listing.deleteId = null;

            if (!this.checkCanBeDeleted(item)) {
                this.showDeleteErrorNotification(item);
            }

            this.mailHeaderFooterRepository.delete(item.id)
                .then(() => {
                    this.$refs.listing.resetSelection();
                    this.$refs.listing.doSearch();
                });
        },

        getMailHeaderFooterCriteria(mailHeaderFooter) {
            const criteria = new Criteria();

            criteria.addFilter(
                Criteria.equalsAny('id', mailHeaderFooter),
            );

            criteria.addAssociation('salesChannels');

            return criteria;
        },

        onMultipleDelete() {
            const selectedMailHeaderFooters = Object.values(this.$refs.listing.selection).map(currentProxy => {
                return currentProxy.id;
            });

            this.mailHeaderFooterRepository
                .search(this.getMailHeaderFooterCriteria(selectedMailHeaderFooters))
                .then(response => {
                    response.forEach((mailHeaderFooter) => {
                        if (!this.checkCanBeDeleted(mailHeaderFooter)) {
                            this.showDeleteErrorNotification(mailHeaderFooter);
                        }
                    });

                    this.$refs.listing.deleteItems();
                });
        },

        showDeleteErrorNotification(item) {
            return this.createNotificationError({
                message: this.$tc('sw-mail-header-footer.list.messageDeleteError', 0, { name: item.name }),
            });
        },

        updateRecords(result) {
            this.mailHeaderFooters = result;
        },
    },
});
