/*
 * @package inventory
 */

import template from './sw-product-settings-mode.html.twig';
import './sw-product-settings-mode.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        modeSettings: {
            type: Object,
            required: true,
        },

        isLoading: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    computed: {
        advancedMode() {
            return this.modeSettings.value.advancedMode;
        },

        settings() {
            switch (this.$route.name) {
                case 'sw.product.detail.base': {
                    return this.modeSettings.value.settings.filter(({ name }) => name === 'general');
                }

                case 'sw.product.detail.specifications': {
                    return this.modeSettings.value.settings.filter(({ name }) => name === 'specifications');
                }

                default: {
                    return this.modeSettings.value.settings;
                }
            }
        },
    },

    methods: {
        onChangeSetting() {
            this.$emit('settings-change');
        },

        onChangeSettingItem() {
            this.$emit('settings-item-change');
        },
    },
};
