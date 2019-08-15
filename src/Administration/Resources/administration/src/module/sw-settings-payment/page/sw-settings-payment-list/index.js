import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-settings-payment-list.html.twig';
import './sw-settings-payment-list.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-settings-payment-list', {
    template,

    inject: [
        'repositoryFactory',
        'context'
    ],

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            entityName: 'payment',
            payment: null,
            sortBy: 'name',
            isLoading: false,
            sortDirection: 'ASC',
            naturalSorting: true,
            showDeleteModal: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        paymentRepository() {
            return this.repositoryFactory.create('payment_method');
        }
    },

    methods: {
        getList() {
            const criteria = new Criteria(this.page, this.limit);
            this.isLoading = true;
            this.naturalSorting = this.sortBy === 'name';

            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));

            this.paymentRepository.search(criteria, this.context).then((items) => {
                this.total = items.total;
                this.payment = items;
                this.isLoading = false;

                return items;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onInlineEditSave(promise, payment) {
            promise.then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-payment.detail.titleSaveSuccess'),
                    message: this.$tc('sw-settings-payment.detail.messageSaveSuccess', 0, { name: payment.name })
                });
            }).catch(() => {
                this.getList();
                this.createNotificationError({
                    title: this.$tc('sw-settings-payment.detail.titleSaveError'),
                    message: this.$tc('sw-settings-payment.detail.messageSaveError')
                });
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

            return this.paymentRepository.delete(id, this.context).then(() => {
                this.getList();
            });
        },

        getPaymentColumns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                inlineEdit: 'string',
                label: this.$tc('sw-settings-payment.list.columnName'),
                routerLink: 'sw.settings.payment.detail',
                width: '250px',
                primary: true
            }, {
                property: 'active',
                inlineEdit: 'string',
                label: this.$tc('sw-settings-payment.list.columnActive')
            }, {
                property: 'description',
                label: this.$tc('sw-settings-payment.list.columnDescription')
            }];
        }
    }
});
