import template from './sw-mail-header-footer-list.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-mail-header-footer-list', {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification')
    ],

    data() {
        return {
            mailHeaderFooters: null,
            showDeleteModal: null,
            isLoading: false
        };
    },

    computed: {
        mailHeaderFooterRepository() {
            return this.repositoryFactory.create('mail_header_footer');
        }
    },

    methods: {
        onEdit(mailHeaderFooter) {
            if (mailHeaderFooter && mailHeaderFooter.id) {
                this.$router.push({
                    name: 'sw.mail.template.detail_head_foot',
                    params: {
                        id: mailHeaderFooter.id
                    }
                });
            }
        },

        getList() {
            this.isLoading = true;
            this.mailHeaderFooters = null;
            const criteria = new Criteria(this.page, this.limit);
            criteria.addAssociation('salesChannels');

            this.mailHeaderFooterRepository.search(criteria, Shopware.Context.api).then((items) => {
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
                primary: true
            }, {
                property: 'description',
                dataIndex: 'description',
                label: 'sw-mail-header-footer.list.columnDescription',
                allowResize: true
            }, {
                property: 'salesChannels.name',
                dataIndex: 'salesChannels.name',
                label: 'sw-mail-header-footer.list.columnSalesChannels',
                allowResize: true,
                sortable: false
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
            this.mailHeaderFooterRepository.clone(id, Shopware.Context.api).then((mailHeaderFooter) => {
                this.$router.push(
                    {
                        name: 'sw.mail.template.detail_head_foot',
                        params: { id: mailHeaderFooter.id }
                    }
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

            this.mailHeaderFooterRepository.delete(item.id, Shopware.Context.api)
                .then(() => {
                    this.$refs.listing.resetSelection();
                    this.$refs.listing.doSearch();
                });
        },

        getMailHeaderFooterCriteria(mailHeaderFooter) {
            const criteria = new Criteria();

            criteria.addFilter(
                Criteria.equalsAny('id', mailHeaderFooter)
            );

            criteria.addAssociation('salesChannels');

            return criteria;
        },

        onMultipleDelete() {
            const selectedMailHeaderFooters = Object.values(this.$refs.listing.selection).map(currentProxy => {
                return currentProxy.id;
            });

            this.mailHeaderFooterRepository
                .search(this.getMailHeaderFooterCriteria(selectedMailHeaderFooters), Shopware.Context.api)
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
                message: this.$tc('sw-mail-header-footer.list.messageDeleteError', 0, { name: item.name })
            });
        }
    }
});
