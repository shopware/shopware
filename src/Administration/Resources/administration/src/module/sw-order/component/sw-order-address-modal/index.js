import { Component, State } from 'src/core/shopware';
// import { object } from 'src/core/service/util.service';
import template from './sw-order-address-modal.html.twig';
import './sw-order-address-modal.less';

Component.register('sw-order-address-modal', {
    template,
    props:
        {
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
            currentlyShowing: 'edit',
            availableAddresses: []
        };
    },
    computed: {
        customerStore() {
            return State.getStore('customer');
        }
    },
    created() {
        this.currentAddress = this.address;

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

        onExistingAddressSelected(address) {
            this.$emit('sw-address-modal-selected-address', address);
        },
        onClose() {
            this.$emit('sw-address-modal-close');
        },
        onSave() {
            this.$emit('sw-address-modal-save');
        }

    }
});
