import template from './sw-address.html.twig';
import './sw-address.scss';

const { Component } = Shopware;

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
            type: Object,
            default() {
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
            type: Object,
            required: false,
            default: null,
        },
    },

    computed: {
        addressClasses() {
            return {
                'sw-address--headline': this.headline,
            };
        },
    },
});
