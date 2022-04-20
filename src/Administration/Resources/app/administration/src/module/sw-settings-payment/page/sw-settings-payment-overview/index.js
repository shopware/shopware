import template from './sw-settings-payment-overview.html.twig';
import './sw-settings-payment-overview.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-payment-overview', {
    template,

    inject: ['repositoryFactory', 'acl'],

    data() {
        return {
            paymentMethods: null,
            isLoading: false,
            showSortingModal: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        paymentMethodRepository() {
            return this.repositoryFactory.create('payment_method');
        },

        paymentMethodCriteria() {
            const criteria = new Criteria(1, 500);

            criteria.addAssociation('plugin');
            criteria.addAssociation('appPaymentMethod.app');

            return criteria;
        },

        isEmpty() {
            return !this.isLoading && (!this.paymentMethods || !this.paymentMethods.total);
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadPaymentMethods();
        },

        loadPaymentMethods() {
            this.isLoading = true;

            this.paymentMethodRepository.search(this.paymentMethodCriteria).then((items) => {
                this.paymentMethods = items;
            }).finally(() => {
                this.isLoading = false;
            });
        },

        onChangeLanguage(languageId) {
            Shopware.State.commit('context/setApiLanguageId', languageId);
            this.loadPaymentMethods();
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
