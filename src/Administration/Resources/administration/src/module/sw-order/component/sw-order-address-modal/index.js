import { Component, State } from 'src/core/shopware';
import template from './sw-order-address-modal.html.twig';
import './sw-order-address-modal.scss';

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
        this.createdComponent();
    },
    methods: {
        createdComponent() {
            this.customerStore.getByIdAsync(this.orderCustomer.customerId).then((customer) => {
                if (customer.isLocal) {
                    this.$emit('sw-address-modal-error', 'Invalid customerid');
                } else {
                    customer.getAssociation('addresses').getList({ page: 1, limit: 25 }).then(() => {
                        this.availableAddresses = customer.addresses;
                    });
                }
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
            this.$emit('sw-address-modal-close');
        },
        onSave() {
            if (this.selectedAddressId !== 0) {
                const address = this.availableAddresses.find((addr) => {
                    return addr.id === this.selectedAddressId;
                });
                this.$emit('sw-address-modal-selected-address', address);
            } else {
                this.$emit('sw-address-modal-save');
            }
        }
    }
});
