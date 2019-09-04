import template from './sw-customer-list.html.twig';
import './sw-customer-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-customer-list', {
    template,

    inject: [
        'repositoryFactory',
        'context'
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('salutation'),
        Mixin.getByName('listing')
    ],

    data() {
        return {
            customers: null,
            sortBy: 'customerNumber',
            sortDirection: 'DESC',
            naturalSorting: true,
            isLoading: false,
            showDeleteModal: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        customerRepository() {
            return this.repositoryFactory.create('customer');
        },

        customerColumns() {
            return this.getCustomerColumns();
        },

        defaultCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            this.naturalSorting = this.sortBy === 'customerNumber';

            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            criteria.addAssociation('defaultBillingAddress');

            return criteria;
        }
    },

    methods: {
        onInlineEditSave(promise, customer) {
            promise.then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-customer.detail.titleSaveSuccess'),
                    message: this.$tc('sw-customer.detail.messageSaveSuccess', 0, { name: this.salutation(customer) })
                });
            }).catch(() => {
                this.getList();
                this.createNotificationError({
                    title: this.$tc('sw-customer.detail.titleSaveError'),
                    message: this.$tc('sw-customer.detail.messageSaveError')
                });
            });
        },

        getList() {
            this.isLoading = true;

            this.customerRepository.search(this.defaultCriteria, this.context).then((items) => {
                this.total = items.total;
                this.customers = items;
                this.isLoading = false;

                return items;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.customerRepository.delete(id, this.context).then(() => {
                this.getList();
            });
        },

        getCustomerColumns() {
            return [{
                property: 'firstName',
                dataIndex: 'firstName,lastName',
                inlineEdit: 'string',
                label: this.$tc('sw-customer.list.columnName'),
                routerLink: 'sw.customer.detail',
                width: '250px',
                allowResize: true,
                primary: true
            }, {
                property: 'defaultBillingAddress.street',
                label: this.$tc('sw-customer.list.columnStreet'),
                allowResize: true
            }, {
                property: 'defaultBillingAddress.zipcode',
                label: this.$tc('sw-customer.list.columnZip'),
                align: 'right',
                allowResize: true
            }, {
                property: 'defaultBillingAddress.city',
                label: this.$tc('sw-customer.list.columnCity'),
                allowResize: true
            }, {
                property: 'customerNumber',
                dataIndex: 'customerNumber',
                naturalSorting: true,
                label: this.$tc('sw-customer.list.columnCustomerNumber'),
                allowResize: true,
                inlineEdit: 'string',
                align: 'right'
            }, {
                property: 'email',
                inlineEdit: 'string',
                label: this.$tc('sw-customer.list.columnEmail'),
                allowResize: true
            }];
        }
    }
});
