import template from './sw-newsletter-recipient-filter-switch.html.twig';
import './sw-newsletter-recipient-filter-switch.scss';

/**
 * @sw-package after-sales
 *
 * @deprecated tag:v6.8.0 - Will be removed without replacement
 *
 * @public
 */
export default {
    template,

    emits: ['update:value'],

    props: {
        id: {
            type: String,
            required: true,
        },

        label: {
            type: String,
            required: false,
            default: '',
        },

        group: {
            type: String,
            required: false,
            default: null,
        },
    },

    methods: {
        onChange(value) {
            this.$emit('update:value', {
                id: this.id,
                group: this.group,
                value,
            });
        },
    },
};
