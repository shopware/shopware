import type { PropType } from 'vue';
import type { Route } from 'vue-router';
import template from './sw-address.html.twig';
import './sw-address.scss';

const { Component } = Shopware;

interface Country {
    name: string,
}

interface CountryState {
    name: string,
}

interface Address {
    salutation: $TSFixMe,
    title: string,
    firstName: string,
    lastName: string,
    street: string,
    zipcode: string,
    city: string,
    country: Country,
    countryState: CountryState,
}

/**
 * @public
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
 * }"></sw-address>
 */
Component.register('sw-address', {
    template,

    props: {
        address: {
            type: Object as PropType<Address>,
            default(): Address {
                return {
                    salutation: '',
                    title: '',
                    firstName: '',
                    lastName: '',
                    street: '',
                    zipcode: '',
                    city: '',
                    country: {
                        name: '',
                    },
                    countryState: {
                        name: '',
                    },
                };
            },
        },

        headline: {
            type: String,
            required: false,
            default: '',
        },

        showEditButton: {
            type: Boolean,
            required: false,
            default: false,
        },

        editLink: {
            type: Object as PropType<Route | null>,
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
    },
});
