import template from './sw-settings-customer-group-detail.html.twig';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-settings-customer-group-detail', {
    template,

    inject: ['repositoryFactory'],

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

        ...mapPropertyErrors('customerGroup', ['name'])
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
                this.customerGroupRepository.get(this.customerGroupId, Shopware.Context.api).then((customerGroup) => {
                    this.customerGroup = customerGroup;
                    this.isLoading = false;
                });
                return;
            }

            Shopware.State.commit('context/resetLanguageToDefault');
            this.customerGroup = this.customerGroupRepository.create(Shopware.Context.api);
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

            return this.customerGroupRepository.save(this.customerGroup, Shopware.Context.api).then(() => {
                this.isSaveSuccessful = true;
                if (!this.customerGroupId) {
                    this.$router.push({ name: 'sw.settings.customer.group.detail', params: { id: this.customerGroup.id } });
                }

                this.customerGroupRepository.get(this.customerGroup.id, Shopware.Context.api)
                    .then((updatedCustomerGroup) => {
                        this.customerGroup = updatedCustomerGroup;
                        this.isLoading = false;
                    });
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('sw-settings-customer-group.detail.notificationErrorMessage')
                });
            }).finally(() => {
                this.isLoading = false;
            });
        }
    }
});
