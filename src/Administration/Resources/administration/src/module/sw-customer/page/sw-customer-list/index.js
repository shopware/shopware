import { Component, Mixin } from 'src/core/shopware';
import template from './sw-customer-list.html.twig';
import './sw-customer-list.scss';

Component.register('sw-customer-list', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('listing')
    ],

    data() {
        return {
            customers: [],
            isLoading: false,
            showDeleteModal: false
        };
    },

    computed: {
        customerStore() {
            return Shopware.State.getStore('customer');
        },

        customerColumns() {
            return this.getCustomerColumns();
        }
    },

    methods: {
        onInlineEditSave(customer) {
            return customer.save().then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-customer.detail.titleSaveSuccess'),
                    message: this.$tc('sw-customer.detail.messageSaveSuccess', 0, { name: this.getFullName(customer) })
                });
            }).catch(() => {
                customer.discardChanges();

                this.createNotificationError({
                    title: this.$tc('sw-customer.detail.titleSaveError'),
                    message: this.$tc('sw-customer.detail.messageSaveError')
                });
            });
        },

        onInlineEditCancel(customer) {
            customer.discardChanges();
        },

        getFullName(customer) {
            if (!customer.salutation && !customer.title && !customer.firstName && !customer.lastName) {
                return '';
            }

            const salutation = customer.salutation ? customer.salutation : '';
            const title = customer.title ? customer.title : '';
            const firstName = customer.firstName ? customer.firstName : '';
            const lastName = customer.lastName ? customer.lastName : '';

            return `${salutation} ${title} ${firstName} ${lastName}`.trim();
        },

        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            this.customers = [];

            // Use the customer number as the default sorting
            if (!params.sortBy && !params.sortDirection) {
                params.sortBy = 'customerNumber';
                params.sortDirection = 'DESC';
            }

            // Use natural sorting when using customer number
            if (params.sortBy === 'customerNumber') {
                params.sortings = [{
                    field: 'customerNumber',
                    direction: 'DESC',
                    naturalSorting: true
                }];
            }

            return this.customerStore.getList(params).then((response) => {
                this.total = response.total;
                this.customers = response.items;
                this.isLoading = false;

                return this.customers;
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

            return this.customerStore.getById(id).delete(true).then(() => {
                this.getList();
            });
        },

        getCustomerColumns() {
            return [{
                property: 'customerNumber',
                dataIndex: 'customerNumber',
                label: this.$tc('sw-customer.list.columnCustomerNumber'),
                allowResize: true,
                inlineEdit: 'string',
                align: 'right'
            }, {
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
                dataIndex: 'defaultBillingAddress.street',
                allowResize: true
            }, {
                property: 'defaultBillingAddress.zipcode',
                dataIndex: 'defaultBillingAddress.zipcode',
                label: this.$tc('sw-customer.list.columnZip'),
                align: 'right',
                allowResize: true
            }, {
                property: 'defaultBillingAddress.city',
                dataIndex: 'defaultBillingAddress.city',
                label: this.$tc('sw-customer.list.columnCity'),
                allowResize: true
            }, {
                property: 'email',
                dataIndex: 'email',
                inlineEdit: 'string',
                label: this.$tc('sw-customer.list.columnEmail'),
                allowResize: true
            }];
        }
    }
});
