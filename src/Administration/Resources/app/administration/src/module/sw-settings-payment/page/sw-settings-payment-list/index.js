import template from './sw-settings-payment-list.html.twig';
import './sw-settings-payment-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const utils = Shopware.Utils;

Component.register('sw-settings-payment-list', {
    template,

    inject: ['repositoryFactory', 'acl', 'feature'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
        Mixin.getByName('position'),
    ],

    data() {
        return {
            entityName: 'payment_method',
            payment: null,
            isLoading: false,
            sortBy: 'position',
            sortDirection: 'ASC',
            naturalSorting: true,
            showDeleteModal: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        paymentRepository() {
            return this.repositoryFactory.create(this.entityName);
        },

        disablePositioning() {
            return (!!this.term) || (this.sortBy !== 'position');
        },
    },

    methods: {
        getList() {
            const criteria = new Criteria(this.page, this.limit);
            this.isLoading = true;
            this.naturalSorting = this.sortBy === 'name';

            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            criteria.addAssociation('plugin');
            criteria.addAssociation('appPaymentMethod.app');

            this.paymentRepository.search(criteria).then((items) => {
                this.total = items.total;
                this.payment = items;
                this.isLoading = false;

                return items;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onChangeLanguage(languageId) {
            Shopware.State.commit('context/setApiLanguageId', languageId);
            this.getList();
        },

        onInlineEditSave(promise, payment) {
            promise.then(() => {
                this.createNotificationSuccess({
                    message: this.$tc('sw-settings-payment.detail.messageSaveSuccess', 0, { name: payment.name }),
                });
            }).catch(() => {
                this.getList();
                this.createNotificationError({
                    message: this.$tc('sw-settings-payment.detail.messageSaveError'),
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

            return this.paymentRepository.delete(id).then(() => {
                this.getList();
            });
        },

        onPositionChanged: utils.debounce(function syncPayment(payment) {
            this.payment = payment;

            this.paymentRepository.sync(payment)
                .then(this.getList)
                .catch(() => {
                    this.getList();
                    this.createNotificationError({
                        message: this.$tc('global.notification.unspecifiedSaveErrorMessage'),
                    });
                });
        }, 800),

        getPaymentColumns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                inlineEdit: 'string',
                label: 'sw-settings-payment.list.columnName',
                routerLink: 'sw.settings.payment.detail',
                width: '250px',
                primary: true,
            }, {
                property: 'extension',
                label: 'sw-settings-payment.list.columnExtension',
            }, {
                property: 'active',
                inlineEdit: 'string',
                label: 'sw-settings-payment.list.columnActive',
            }, {
                property: 'description',
                label: 'sw-settings-payment.list.columnDescription',
            }, {
                property: 'position',
                label: 'sw-settings-payment.list.columnPosition',
            }];
        },

        getExtensionName(paymentMethod) {
            if (paymentMethod.plugin) {
                return paymentMethod.plugin.translated.label;
            }

            if (paymentMethod.appPaymentMethod) {
                return paymentMethod.appPaymentMethod.app.translated.label;
            }

            return null;
        },
    },
});
