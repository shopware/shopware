import { Component, State, Mixin } from 'src/core/shopware';
import template from './sw-settings-customer-group-detail.html.twig';

Component.register('sw-settings-customer-group-detail', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('customerGroup')
    ],

    data() {
        return {
            entityName: 'customer_group',
            isLoading: false,
            customerGroup: null,
            isSaveSuccessful: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.customerGroup, 'name');
        },

        customerGroupStore() {
            return State.getStore('customer_group');
        },

        entityDescription() {
            return this.placeholder(
                this.customerGroup,
                'name',
                this.$tc('sw-settings-customer-group.detail.placeholderNewCustomerGroup')
            );
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.customerGroup = this.customerGroupStore.getById(this.$route.params.id);
        },

        onChangeLanguage() {
            this.createdComponent();
        },

        onCancel() {
            this.discardChanges();
            this.$router.push({ name: 'sw.settings.customer.group.index' });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;
            this.customerGroup.name = this.customerGroup.name.trim();

            return this.customerGroup.save().then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-customer-group.general.titleError'),
                    message: this.$tc('sw-settings-customer-group.detail.messageSaveError')
                });
            }).finally(() => {
                this.isLoading = false;
            });
        }
    }


});
