import template from './sw-order-user-card.html.twig';
import './sw-order-user-card.scss';

/**
 * @package checkout
 */

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const ApiService = Shopware.Classes.ApiService;
const format = Shopware.Utils.format;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'customSnippetApiService',
        'orderService',
        'repositoryFactory',
        'feature',
    ],

    mixins: [
        Mixin.getByName('salutation'),
    ],

    props: {
        currentOrder: {
            type: Object,
            required: true,
        },
        versionContext: {
            type: Object,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: true,
        },
        isEditing: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            addressBeingEdited: null,
            countries: null,
            formattingAddress: '',
        };
    },

    computed: {
        countryRepository() {
            return this.repositoryFactory.create('country');
        },

        orderAddressRepository() {
            return this.repositoryFactory.create('order_address');
        },

        OrderTagRepository() {
            return this.repositoryFactory.create(
                this.currentOrder.tags.entity,
                this.currentOrder.tags.source,
            );
        },

        billingAddress() {
            return this.currentOrder.addresses.find((address) => {
                return address.id === this.currentOrder.billingAddressId;
            });
        },

        delivery() {
            return this.currentOrder.deliveries[0];
        },

        orderDate() {
            if (this.currentOrder && !this.currentOrder.isLoading) {
                return format.date(this.currentOrder.orderDateTime);
            }
            return '';
        },

        hasDeliveries() {
            return this.currentOrder.deliveries.length > 0;
        },

        hasDeliveryTrackingCode() {
            return this.hasDeliveries && this.delivery.trackingCodes.length;
        },

        hasDifferentBillingAndShippingAddress() {
            return this.hasDeliveries &&
                this.billingAddress.id !== this.delivery.shippingOrderAddressId;
        },

        lastChangedDate() {
            if (this.currentOrder) {
                if (this.currentOrder.updatedAt) {
                    return format.date(
                        this.currentOrder.updatedAt,
                    );
                }

                return format.date(
                    this.currentOrder.orderDateTime,
                );
            }
            return '';
        },

        hasTags() {
            return this.currentOrder.tags.length !== 0;
        },

        fullName() {
            const name = {
                name: this.salutation(this.currentOrder.orderCustomer),
                company: this.currentOrder.orderCustomer.company,
            };

            return Object.values(name).filter(item => item !== null).join(' - ').trim();
        },

        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.reload();
            this.renderFormattingAddress();
        },

        renderFormattingAddress() {
            this.customSnippetApiService.render(
                this.billingAddress,
                this.billingAddress.country?.addressFormat,
            ).then((res) => {
                this.formattingAddress = res.rendered;
            });
        },

        reload() {
            this.countryRepository.search(this.countryCriteria()).then((response) => {
                this.countries = response;
            });
        },

        countryCriteria() {
            const criteria = new Criteria(1, 100);
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },

        onEditBillingAddress() {
            if (this.isEditing) {
                this.addressBeingEdited = this.billingAddress;
            }
        },

        onEditDeliveryAddress() {
            if (this.isEditing) {
                this.addressBeingEdited = this.delivery.shippingOrderAddress;
            }
        },

        onAddressModalSave() {
            this.addressBeingEdited = null;
            this.$nextTick(() => {
                this.emitChange();
            });
        },

        onResetOrder() {
            this.addressBeingEdited = null;
            this.$nextTick(() => {
                this.$emit('order-reset');
            });
        },

        onAddressModalAddressSelected(address) {
            const oldAddressId = this.addressBeingEdited.id;
            this.addressBeingEdited = null;
            this.$nextTick(() => {
                return this.orderService.changeOrderAddress(
                    oldAddressId,
                    address.id,
                    {},
                    ApiService.getVersionHeader(this.currentOrder.versionId),
                ).then(() => {
                    this.emitChange();
                }).catch((error) => {
                    this.$emit('error', error);
                });
            });
        },

        onAddNewDeliveryAddress() {
            if (!this.isEditing) {
                return;
            }

            this.orderAddressRepository.clone(
                this.delivery.shippingOrderAddressId,
                this.versionContext,
            ).then((response) => {
                this.delivery.shippingOrderAddressId = response.id;
                this.emitChange();
            }).catch((error) => {
                this.$emit('error', error);
            });
        },

        emitChange() {
            this.$emit('order-change');
        },

        onAddTag(item) {
            this.OrderTagRepository.assign(item.id, Shopware.Context.api).then(() => {
                this.emitChange();
            });
        },

        onRemoveTag(item) {
            this.OrderTagRepository.delete(item.id).then(() => {
                this.emitChange();
            });
        },

        renderTrackingUrl(trackingCode, shippingMethod) {
            const urlTemplate = shippingMethod ? shippingMethod.trackingUrl : null;

            return urlTemplate ? urlTemplate.replace('%s', encodeURIComponent(trackingCode)) : '';
        },
    },

};
