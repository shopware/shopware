import { Component, Mixin } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-order-address-modal.html.twig';
import './sw-order-address-modal.scss';

Component.register('sw-order-address-modal', {
    template,

    inject: [
        'repositoryFactory',
        'context'
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        address: {
            type: Object,
            required: true,
            default: {}
        },

        countries: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        },

        order: {
            type: Object,
            required: true,
            default: {}
        },

        versionContext: {
            type: Object,
            required: true,
            default: {}
        }
    },
    data() {
        return {
            availableAddresses: [],
            selectedAddressId: 0,
            isLoading: false
        };
    },
    computed: {
        customerRepository() {
            return this.repositoryFactory.create('customer');
        },

        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        orderCustomer() {
            return this.order.orderCustomer;
        }
    },
    created() {
        this.createdComponent();
    },
    methods: {
        createdComponent() {
            this.isLoading = true;

            this.customerRepository.search(this.customerCriteria(), this.context).then((customer) => {
                this.availableAddresses = customer[0].addresses;

                return this.$store.dispatch('resetApiErrors');
            }).finally(() => {
                this.isLoading = false;
            });
        },

        customerCriteria() {
            const criteria = new Criteria({ page: 1, limit: 1 });
            criteria.setIds([this.orderCustomer.customerId]);
            criteria.addAssociation('addresses');

            return criteria;
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
            this.$nextTick(() => {
                this.$emit('close');
            });
        },

        onSave() {
            this.isLoading = true;

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
                            title: this.$tc('sw-order.detail.titleSaveError'),
                            message: this.$tc('sw-order.detail.messageSaveError')
                        });
                    }).finally(() => {
                        resolve();
                    });
                }
            }).finally(() => {
                this.isLoading = false;
            });
        }
    }
});
