import template from './sw-order-create-address-modal.html.twig';
import './sw-order-create-address-modal.scss';

const { Component, Mixin, State, Service } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-order-create-address-modal', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
    ],

    props: {
        customer: {
            type: Object,
            required: true,
        },
        address: {
            type: Object,
            required: true,
        },
        addAddressModalTitle: {
            type: String,
            required: true,
        },
        editAddressModalTitle: {
            type: String,
            required: true,
        },
        cart: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            addresses: [],
            selectedAddressId: null,
            activeCustomer: this.customer,
            isLoading: false,
            term: null,
            showAddressFormModal: false,
            defaultAddressIdMapping: {
                'billing-address': 'defaultBillingAddressId',
                'shipping-address': 'defaultShippingAddressId',
            },
            currentAddress: null,
        };
    },

    computed: {
        addressCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('salutation');
            criteria.addAssociation('country');
            criteria.addAssociation('countryState');

            if (this.term) {
                criteria.setTerm(this.term);
            }

            return criteria;
        },

        customerRepository() {
            return Service('repositoryFactory').create('customer');
        },

        addressRepository() {
            return Service('repositoryFactory').create(
                this.activeCustomer.addresses.entity,
                this.activeCustomer.addresses.source,
            );
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            await this.getCustomerAddresses();
        },

        async getCustomerAddresses() {
            this.isLoading = true;

            // Get the latest addresses from customer's db
            try {
                this.addresses = await this.addressRepository.search(this.addressCriteria);

                this.selectedAddressId = this.activeCustomer[this.address.contextId]
                    || this.activeCustomer[this.address.contextDataDefaultId];

                await Shopware.State.dispatch('error/resetApiErrors');
            } catch {
                this.createNotificationError({
                    message: this.$tc('sw-order.create.messageFetchCustomerAddressesError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        onNewActiveItem() {
            this.selectedAddressId = null;
        },

        isCurrentSelected(addressId) {
            return this.selectedAddressId === addressId;
        },

        async onSearchAddress(term) {
            this.term = term;
            await this.getCustomerAddresses();
        },

        async onSelectExistingAddress(address) {
            this.selectedAddressId = address.id;
            await this.onSave();
        },

        findSelectedAddress() {
            return this.addresses.find(address => address.id === this.selectedAddressId);
        },

        async updateOrderContext() {
            const address = this.findSelectedAddress();

            const context = {
                [this.address.contextId]: address.id,
                [this.address.contextDataKey]: address,
                [this.address.contextDataDefaultId]: address[this.address.contextDataDefaultId],
            };

            await State
                .dispatch('swOrder/updateOrderContext', {
                    context,
                    salesChannelId: this.activeCustomer.salesChannelId,
                    contextToken: this.cart.token,
                });

            this.$emit('set-customer-address', {
                contextId: this.address.contextId,
                contextDataKey: this.address.contextDataKey,
                data: address,
            });
        },

        async saveCurrentCustomer() {
            if (this.hasOwnProperty('defaultShippingAddressId')) {
                this.activeCustomer.defaultShippingAddressId = this.defaultShippingAddressId;
            }

            if (this.hasOwnProperty('defaultBillingAddressId')) {
                this.activeCustomer.defaultBillingAddressId = this.defaultBillingAddressId;
            }

            return this.customerRepository.save(this.activeCustomer);
        },

        async saveCurrentAddress() {
            this.selectedAddressId = this.currentAddress.id;

            if (this.currentAddress.isNew()) {
                this.addresses.push(this.currentAddress);
            }

            return this.addressRepository.save(this.currentAddress);
        },

        closeModal() {
            this.$emit('close-modal');
        },

        onCancel() {
            this.closeModal();
        },

        async onSave() {
            this.isLoading = true;

            try {
                await this.updateOrderContext();
                this.closeModal();
            } catch {
                this.createNotificationError({
                    message: this.$tc('sw-order.detail.messageSaveError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        onCloseAddressModal() {
            this.showAddressFormModal = false;
        },

        onAddNewAddress() {
            this.createNewCustomerAddress();
            this.showAddressFormModal = true;
        },

        onEditAddress(address) {
            this.currentAddress = address;
            this.showAddressFormModal = true;
        },

        onChangeDefaultAddress(data) {
            if (!data.value) {
                return;
            }

            const name = this.defaultAddressIdMapping[data.name];

            this[name] = data.id;
        },

        async onSubmitAddressForm() {
            try {
                this.isLoading = true;

                if (this.currentAddress === null) {
                    return;
                }

                await this.saveCurrentAddress();
                await this.saveCurrentCustomer();
                await this.updateOrderContext();
                await this.getCustomerAddresses();

                this.currentAddress = null;
                this.showAddressFormModal = false;
            } catch {
                this.createNotificationError({
                    message: this.$tc('sw-order.detail.messageSaveError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        getAddressFormModalTitle() {
            return !this.currentAddress || this.currentAddress.isNew()
                ? this.addAddressModalTitle
                : this.editAddressModalTitle;
        },

        createNewCustomerAddress() {
            const newAddress = this.addressRepository.create();
            newAddress.customerId = this.activeCustomer.id;

            this.currentAddress = newAddress;
        },
    },
});
