import './sw-order-create-general-info.scss';
import template from './sw-order-create-general-info.html.twig';
import {
    aclService,
    type Cart,
    getCurrencyFilter,
    notificationMixin,
    type SalesChannelContext,
    wrapComponentConfig,
} from '../dependencies';

/**
 * @sw-package checkout
 */

/**
 * @private
 */
export default wrapComponentConfig({
    template,

    inject: [aclService],

    mixins: [notificationMixin],

    props: {
        cart: {
            type: Object as PropType<Cart>,
            required: true,
        },
        context: {
            type: Object as PropType<SalesChannelContext>,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    computed: {
        summaryMainHeader(): string {
            if (!this.context.customer) {
                return '';
            }

            return `${this.context.customer.firstName} ${this.context.customer.lastName} (${this.context.customer.email})`;
        },

        paymentMethodName(): string {
            return this.context.paymentMethod?.translated?.distinguishableName ?? '';
        },

        shippingMethodName(): string {
            return this.context.shippingMethod.translated?.name ?? '';
        },

        currencyFilter() {
            return getCurrencyFilter();
        },
    },
});
