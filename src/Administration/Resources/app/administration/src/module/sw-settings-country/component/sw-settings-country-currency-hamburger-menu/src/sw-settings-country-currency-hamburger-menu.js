/**
 * @sw-package fundamentals@discovery
 */
import template from './sw-settings-country-currency-hamburger-menu.html.twig';
import './sw-settings-country-currency-hamburger-menu.scss';
import { aclService } from '../dependencies';

/**
 * @public
 */
export default {
    template,

    inject: [aclService],

    emits: ['currency-change'],

    props: {
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
        options: {
            type: Array,
            required: true,
        },
    },

    methods: {
        onCheckCurrency(currencyId, isChecked) {
            this.$emit('currency-change', currencyId, isChecked);
        },
    },
};
