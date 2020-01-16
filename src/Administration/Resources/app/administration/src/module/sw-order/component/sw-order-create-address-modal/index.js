import template from './sw-order-create-address-modal.html.twig';

const { Component, Mixin, State, Service } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-order-create-address-modal', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    props: {
        customer: {
            type: Object,
            required: true
        },
        address: {
            type: Object,
            required: true
        },
        cart: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            addresses: [],
            selectedAddressId: null,
            isLoading: false
        };
    },

    computed: {
        customerCriteria() {
            const criteria = new Criteria({ page: 1, limit: 1 });
            criteria.setIds([this.customer.id]);
            criteria.addAssociation('addresses');

            return criteria;
        },

        customerRepository() {
            return Service('repositoryFactory').create('customer');
        },

        customerAddressRepository() {
            return Service('repositoryFactory').create('customer_address');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            // Get the latest addresses from customer's db
            this.customerRepository
                .search(this.customerCriteria, Shopware.Context.api)
                .then(customer => {
                    this.addresses = customer[0].addresses;

                    return Shopware.State.dispatch('error/resetApiErrors');
                })
                .catch(() => {
                    this.createNotificationError({
                        title: this.$tc('sw-order.create.titleFetchError'),
                        message: this.$tc('sw-order.create.messageFetchCustomerAddressesError')
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onNewActiveItem() {
            this.selectedAddressId = null;
        },

        addressButtonClasses(addressId) {
            return {
                'sw-order-address-modal__entry__selected': addressId === this.selectedAddressId
            };
        },

        onSelectExistingAddress(address) {
            this.selectedAddressId = address.id;
        },

        findSelectedAddress() {
            return this.addresses.find(address => address.id === this.selectedAddressId);
        },

        updateOrderContext() {
            const address = this.findSelectedAddress();
            const context = {
                [this.address.contextId]: address.id,
                [this.address.contextDataKey]: address
            };

            return State
                .dispatch('swOrder/updateOrderContext', {
                    context,
                    salesChannelId: this.customer.salesChannelId,
                    contextToken: this.cart.token
                })
                .then(() => {
                    this.$emit('set-customer-address', {
                        contextId: this.address.contextId,
                        contextDataKey: this.address.contextDataKey,
                        data: address
                    });
                    this.save();
                })
                .catch(() => {
                    this.createNotificationError({
                        title: this.$tc('sw-order.detail.titleSaveError'),
                        message: this.$tc('sw-order.detail.messageSaveError')
                    });
                });
        },

        modifyCurrentAddress() {
            return this.customerAddressRepository
                .save(this.address.data, Shopware.Context.api)
                .then(() => {
                    this.$emit('set-customer-address', {
                        contextId: this.address.contextId,
                        contextDataKey: this.address.contextDataKey,
                        data: this.address.data
                    });
                    this.save();
                })
                .catch(() => {
                    this.createNotificationError({
                        title: this.$tc('sw-order.detail.titleSaveError'),
                        message: this.$tc('sw-order.detail.messageSaveError')
                    });
                });
        },

        save() {
            this.$emit('save');
        },

        reset() {
            this.$emit('reset');
        },

        onCancel() {
            this.reset();
        },

        onSave() {
            this.isLoading = true;

            new Promise((resolve, reject) => {
                // Check if user selected an address
                if (this.selectedAddressId !== null) {
                    this.updateOrderContext()
                        .then(resolve)
                        .catch(reject);
                } else {
                    this.modifyCurrentAddress()
                        .then(resolve)
                        .catch(reject);
                }
            }).finally(() => {
                this.isLoading = false;
            });
        }
    }
});
