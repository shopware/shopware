import template from './sw-payment-card.html.twig';
import './sw-payment-card.scss';
import { aclService } from '../dependencies';

/**
 * @sw-package checkout
 *
 * @public
 */
export default {
    template,

    inject: [aclService],

    emits: ['set-payment-active'],

    props: {
        paymentMethod: {
            type: Object,
            required: true,
        },
    },

    computed: {
        previewUrl() {
            return this.paymentMethod.media ? this.paymentMethod.media.url : null;
        },
    },

    methods: {
        setPaymentMethodActive(active) {
            if (this.paymentMethod.active === active) {
                return;
            }

            this.paymentMethod.active = active;

            this.$emit('set-payment-active', this.paymentMethod);
        },
    },
};
