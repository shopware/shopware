import type { PropType } from 'vue';
import type { RouteLocationNamedRaw } from 'vue-router';
import type { Address } from 'src/core/service/api/custom-snippet.api.service';
import template from './sw-address.html.twig';
import './sw-address.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @description Component to render a postal address
 * @status ready
 * @example-type static
 * @component-example
 * <sw-address headline="Billing address" :address="{
 *     salutation: 'Mister',
 *     title: 'Doctor',
 *     firstName: 'John',
 *     lastName: 'Doe',
 *     street: 'Main St 123',
 *     zipcode: '12456',
 *     city: 'Anytown',
 *     country: { name: 'Germany' }
 * }" :formattingAddress="First Name Last Name\nGermany"></sw-address>
 */
Component.register('sw-address', {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        address: {
            type: Object as PropType<Address>,
            default(): Address {
                return {
                    salutation: {
                        displayName: '',
                        translated: {
                            displayName: '',
                        },
                    },
                    title: '',
                    firstName: '',
                    lastName: '',
                    street: '',
                    zipcode: '',
                    city: '',
                    country: {
                        name: '',
                        translated: {
                            name: '',
                        },
                    },
                    countryState: {
                        name: '',
                        translated: {
                            name: '',
                        },
                    },
                };
            },
        },

        headline: {
            type: String,
            required: false,
            default: '',
        },

        formattingAddress: {
            type: String,
            required: false,
            default: null,
        },

        showEditButton: {
            type: Boolean,
            required: false,
            default: false,
        },

        editLink: {
            type: Object as PropType<RouteLocationNamedRaw | null>,
            required: false,
            default: null,
        },
    },

    computed: {
        addressClasses(): Record<string, boolean | string> {
            return {
                'sw-address--headline': this.headline,
            };
        },

        displayFormattingAddress(): string {
            return this.formattingAddress;
        },
    },
});
