import template from './sw-product-settings-mode.html.twig';
import './sw-product-settings-mode.scss';

const { Component } = Shopware;

Component.register('sw-product-settings-mode', {
    template,

    props: {
        modeSettings: {
            type: Object,
            required: true
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: true
        }
    },

    computed: {
        advancedMode() {
            return this.modeSettings.value.advancedMode;
        },

        settings() {
            switch (this.$route.name) {
                case 'sw.product.detail.base': {
                    return this.modeSettings.value.settings.filter(item => item.tabSetting === 'general');
                }

                case 'sw.product.detail.specifications': {
                    return this.modeSettings.value.settings.filter(item => item.tabSetting === 'specifications');
                }

                default: {
                    return this.modeSettings.value.settings;
                }
            }
        }
    },

    methods: {
        onChangeModeSettings() {
            this.$emit('settings-change');
        }
    }
});
