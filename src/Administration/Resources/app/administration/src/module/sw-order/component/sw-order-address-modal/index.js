import template from './sw-order-address-modal.html.twig';
import './sw-order-address-modal.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-order-address-modal', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
    ],

    props: {
        address: {
            type: Object,
            required: true,
            default: () => {},
        },

        countries: {
            type: Array,
            required: true,
            default() {
                return [];
            },
        },

        order: {
            type: Object,
            required: true,
            default: () => {},
        },

        versionContext: {
            type: Object,
            required: true,
            default: () => {},
        },
    },

    data() {
        return {
            availableAddresses: [],
            selectedAddressId: 0,
            isLoading: false,
        };
    },

    computed: {
        customerCriteria() {
            const criteria = new Criteria({ page: 1, limit: 1 });
            criteria.setIds([this.orderCustomer.customerId]);
            criteria.addAssociation('addresses');

            return criteria;
        },

        customerRepository() {
            return this.repositoryFactory.create('customer');
        },

        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        orderCustomer() {
            return this.order.orderCustomer;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.orderCustomer && this.orderCustomer.customerId) {
                this.getCustomerInfo();
            }
        },

        getCustomerInfo() {
            this.isLoading = true;

            this.customerRepository.search(this.customerCriteria).then((customer) => {
                this.availableAddresses = customer[0].addresses;

                return Shopware.State.dispatch('error/resetApiErrors');
            }).finally(() => {
                this.isLoading = false;
            });
        },

        onNewActiveItem() {
            this.selectedAddressId = 0;
        },

        addressButtonClasses(addressId) {
            return `sw-order-address-modal__entry${addressId === this.selectedAddressId ?
                ' sw-order-address-modal__entry__selected' : ''}`;
        },

        onExistingAddressSelected(address) {
            this.selectedAddressId = address.id;
        },

        onClose() {
            this.$emit('reset');
        },

        onSave() {
            this.isLoading = true;

            const isShippingAvailable = this.order.addresses[0].country.shippingAvailable;
            if (!isShippingAvailable && typeof isShippingAvailable === 'boolean') {
                this.createNotificationError({
                    message: this.$tc('sw-order.detail.messageShippingNotAvailable'),
                });

                this.isLoading = false;
                return;
            }

            new Promise((resolve) => {
                // check if user selected an address
                if (this.selectedAddressId !== 0) {
                    const address = this.availableAddresses.find((addr) => {
                        return addr.id === this.selectedAddressId;
                    });

                    this.$emit('address-select', address);
                    resolve();
                } else {
                    // save address
                    this.orderRepository.save(this.order, this.versionContext).then(() => {
                        this.$emit('save');
                    }).catch(() => {
                        this.createNotificationError({
                            message: this.$tc('sw-order.detail.messageSaveError'),
                        });
                    }).finally(() => {
                        resolve();
                    });
                }
            }).finally(() => {
                this.isLoading = false;
            });
        },
    },
});
