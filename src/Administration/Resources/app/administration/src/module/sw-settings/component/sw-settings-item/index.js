/**
 * @package services-settings
 */
import template from './sw-settings-item.html.twig';
import './sw-settings-item.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        label: {
            required: true,
            type: String,
        },
        to: {
            required: true,
            type: Object,
            default() {
                return {};
            },
        },
        disabled: {
            required: false,
            type: Boolean,
            default: false,
        },
        backgroundEnabled: {
            required: false,
            type: Boolean,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    computed: {
        classes() {
            return {
                'is--disabled': this.disabled,
            };
        },
    },
};
