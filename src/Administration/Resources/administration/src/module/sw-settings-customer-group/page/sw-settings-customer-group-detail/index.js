import { mapApiErrors } from 'src/app/service/map-errors.service';
import template from './sw-settings-customer-group-detail.html.twig';

const { Component, State, Mixin } = Shopware;

Component.register('sw-settings-customer-group-detail', {
    template,

    inject: ['repositoryFactory', 'context'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('customerGroup')
    ],

    props: {
        customerGroupId: {
            type: String,
            required: false,
            default: null
        }
    },

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel'
    },

    data() {
        return {
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
            return this.placeholder(this.customerGroup, 'name', '');
        },

        languageStore() {
            return State.getStore('language');
        },

        customerGroupRepository() {
            return this.repositoryFactory.create('customer_group');
        },

        entityDescription() {
            return this.placeholder(
                this.customerGroup,
                'name',
                this.$tc('sw-settings-customer-group.detail.placeholderNewCustomerGroup')
            );
        },

        tooltipSave() {
            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light'
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light'
            };
        },

        ...mapApiErrors('customerGroup', ['name'])
    },

    watch: {
        customerGroupId() {
            if (!this.customerGroupId) {
                this.createdComponent();
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            if (this.customerGroupId) {
                this.customerGroupRepository.get(this.customerGroupId, this.context).then((customerGroup) => {
                    this.customerGroup = customerGroup;
                    this.isLoading = false;
                });
                return;
            }

            if (this.languageStore.getCurrentId() !== this.languageStore.systemLanguageId) {
                this.languageStore.setCurrentId(this.languageStore.systemLanguageId);
            }

            this.customerGroup = this.customerGroupRepository.create(this.context);
            this.isLoading = false;
        },

        onChangeLanguage() {
            this.createdComponent();
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.customer.group.index' });
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            return this.customerGroupRepository.save(this.customerGroup, this.context).then(() => {
                this.isSaveSuccessful = true;
                if (!this.customerGroupId) {
                    this.$router.push({ name: 'sw.settings.customer.group.detail', params: { id: this.customerGroup.id } });
                }

                this.customerGroupRepository.get(this.customerGroup.id, this.context).then((updatedCustomerGroup) => {
                    this.customerGroup = updatedCustomerGroup;
                    this.isLoading = false;
                });
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-customer-group.detail.notificationErrorTitle'),
                    message: this.$tc('sw-settings-customer-group.detail.notificationErrorMessage')
                });
            }).finally(() => {
                this.isLoading = false;
            });
        }
    }
});
