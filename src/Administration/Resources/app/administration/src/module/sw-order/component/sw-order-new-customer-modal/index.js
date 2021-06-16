import template from './sw-order-new-customer-modal.html.twig';
import './sw-order-new-customer-modal.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-order-new-customer-modal', {
    template,

    inject: ['repositoryFactory', 'numberRangeService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            customer: null,
            salesChannels: null,
            isLoading: false,
            customerNumberPreview: '',
        };
    },

    computed: {
        customerRepository() {
            return this.repositoryFactory.create('customer');
        },

        addressRepository() {
            return this.repositoryFactory.create('customer_address');
        },

        shippingAddress() {
            if (this.isSameBilling) {
                return this.billingAddress;
            }

            return this.customer !== null ? this.customer.addresses.get(this.customer.defaultShippingAddressId) : null;
        },

        billingAddress() {
            return this.customer !== null ? this.customer.addresses.get(this.customer.defaultBillingAddressId) : null;
        },

        isSameBilling: {
            get() {
                if (this.customer === null) {
                    return true;
                }

                return this.customer.defaultBillingAddressId === this.customer.defaultShippingAddressId;
            },

            set(newValue) {
                if (newValue === true) {
                    this.customer.defaultShippingAddressId = this.customer.defaultBillingAddressId;

                    // remove all addresses but default billing...
                    if (this.customer.isNew()) {
                        this.customer.addresses = this.customer.addresses.filter((address) => {
                            return address.id === this.customer.defaultBillingAddressId;
                        });
                    }

                    return;
                }

                const shippingAddress = this.addressRepository.create();
                this.customer.addresses.add(shippingAddress);
                this.customer.defaultShippingAddressId = shippingAddress.id;
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.customer = this.customerRepository.create();

            const billingAddress = this.addressRepository.create();
            this.customer.addresses.add(billingAddress);

            this.customer.defaultShippingAddressId = billingAddress.id;
            this.customer.defaultBillingAddressId = billingAddress.id;

            this.customer.vatIds = [];
        },

        onSave() {
            if (this.customerNumberPreview) {
                return this.numberRangeService.reserve('customer', this.customer.salesChannelId)
                    .then(() => this.saveCustomer());
            }

            return this.saveCustomer();
        },

        saveCustomer() {
            return this.customerRepository.save(this.customer).then(() => {
                this.$emit('on-select-existing-customer', this.customer.id);
                this.isLoading = false;
                this.onClose();
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('sw-customer.detail.messageSaveError'),
                });
                this.isLoading = false;
            });
        },

        onChangeSalesChannel(salesChannelId) {
            this.customer.salesChannelId = salesChannelId;
            this.numberRangeService.reserve('customer', salesChannelId, true).then((response) => {
                this.customerNumberPreview = response.number;
                this.customer.customerNumber = response.number;
            });
        },

        onClose() {
            this.$emit('close');
        },
    },
});
