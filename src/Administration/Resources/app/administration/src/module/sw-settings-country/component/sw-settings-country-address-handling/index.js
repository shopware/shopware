import template from './sw-settings-country-address-handling.html.twig';
import './sw-settings-country-address-handling.scss';
import { FORMAT_ADDRESS_TEMPLATE, ADDRESS_VARIABLES } from '../../constant/address.constant';

const { Component } = Shopware;

Component.register('sw-settings-country-address-handling', {
    template,

    inject: [
        'acl',
    ],

    props: {
        country: {
            type: Object,
            required: true,
        },

        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            editorConfig: {
                enableBasicAutocompletion: true,
            },
            addressFormat: null,
            advancedPostalCodePattern: null,
        };
    },

    computed: {
        outerCompleterFunction() {
            return (function completerWrapper(variables) {
                function completerFunction(prefix) {
                    const properties = [];
                    variables.forEach(variable => {
                        if (variable.includes(prefix)) {
                            properties.push({
                                value: variable,
                            });
                        }
                    });

                    return properties;
                }

                return completerFunction;
            }(ADDRESS_VARIABLES));
        },
    },

    watch: {
        'country.checkPostalCodePattern'(value) {
            if (value) {
                return;
            }

            this.$set(this.country, 'checkAdvancedPostalCodePattern', false);
        },

        'country.useDefaultAddressFormat': {
            handler(value) {
                if (value) {
                    this.addressFormat = this.country.advancedAddressFormatPlain;
                    this.$set(this.country, 'advancedAddressFormatPlain', FORMAT_ADDRESS_TEMPLATE);
                    return;
                }

                this.$set(this.country, 'advancedAddressFormatPlain', this.addressFormat);
            },
            immediate: true,
        },

        'country.checkAdvancedPostalCodePattern'(value) {
            if (value) {
                if (this.country.advancedPostalCodePattern && !this.advancedPostalCodePattern) {
                    return;
                }

                this.$set(this.country, 'advancedPostalCodePattern', this.advancedPostalCodePattern);
                return;
            }

            this.advancedPostalCodePattern = this.country.advancedPostalCodePattern;
            this.$set(this.country, 'advancedPostalCodePattern', null);
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.addressFormat = this.country.useDefaultAddressFormat
                ? FORMAT_ADDRESS_TEMPLATE
                : this.country.advancedAddressFormatPlain;

            this.advancedPostalCodePattern = this.country.advancedPostalCodePattern;
        },
    },
});
