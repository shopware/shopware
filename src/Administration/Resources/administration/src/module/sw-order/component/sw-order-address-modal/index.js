import { Component, State } from 'src/core/shopware';
import template from './sw-order-address-modal.html.twig';
import './sw-order-address-modal.less';

Component.register('sw-order-address-modal', {
    template,
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
        orderCustomer: {
            type: Object,
            required: true,
            default: {}
        }
    },
    data() {
        return {
            availableAddresses: [],
            selectedAddressId: 0
        };
    },
    computed: {
        customerStore() {
            return State.getStore('customer');
        }
    },
    created() {
        this.customerStore.getByIdAsync(this.orderCustomer.customerId).then((customer) => {
            if (customer.isLocal) {
                console.log('No Customer found');
            } else {
                customer.getAssociation('addresses').getList({ page: 1, limit: 25 }).then(() => {
                    this.availableAddresses = customer.addresses;
                });
            }
        });
    },
    methods: {
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
            this.$emit('sw-address-modal-close');
        },
        onSave() {
            if (this.selectedAddressId !== 0) {
                let address = null;
                this.availableAddresses.forEach((addr) => {
                    if (addr.id === this.selectedAddressId) {
                        address = addr;
                    }
                });
                this.$emit('sw-address-modal-selected-address', address);
            } else {
                this.$emit('sw-address-modal-save');
            }
        }
    }
});
