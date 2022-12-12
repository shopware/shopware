import template from './sw-order-address-modal.html.twig';
import './sw-order-address-modal.scss';

/**
 * @package customer-order
 */

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
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
            addressCustomFieldSets: [],
        };
    },

    computed: {
        customerCriteria() {
            const criteria = new Criteria(1, 1);
            criteria.setIds([this.orderCustomer.customerId]);
            criteria.addAssociation('addresses');

            return criteria;
        },

        customFieldSetCriteria() {
            const criteria = new Criteria(1, null);
            criteria.addFilter(Criteria.equals('relations.entityName', 'customer_address'));
            criteria.addAssociation('customFields');

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

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
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

            this.getCustomFieldSetData();
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

        getCustomFieldSetData() {
            this.customFieldSetRepository.search(this.customFieldSetCriteria).then((response) => {
                this.addressCustomFieldSets = response;
            });
        },
    },
};
