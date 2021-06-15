import template from './sw-customer-address-form.html.twig';
import './sw-customer-address-form.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-customer-address-form', {
    template,

    inject: ['repositoryFactory'],

    props: {
        customer: {
            type: Object,
            required: true,
        },

        address: {
            type: Object,
            required: true,
            default() {
                return this.addressRepository.create(this.context);
            },
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            country: null,
        };
    },

    computed: {
        addressRepository() {
            return this.repositoryFactory.create(
                this.customer.addresses.entity,
                this.customer.addresses.source,
            );
        },

        countryRepository() {
            return this.repositoryFactory.create('country');
        },

        ...mapPropertyErrors('address', [
            'company',
            'department',
            'salutationId',
            'title',
            'firstName',
            'lastName',
            'street',
            'additionalAddressLine1',
            'additionalAddressLine2',
            'zipcode',
            'city',
            'countryId',
            'phoneNumber',
            'vatId',
        ]),

        ...mapPropertyErrors('address', [
            'countryStateId',
            'countryId',
            'salutationId',
            'city',
            'street',
            'zipcode',
            'lastName',
            'firstName',
        ]),

        countryId: {
            get() {
                return this.address.countryId;
            },

            set(countryId) {
                this.address.countryId = countryId;
            },
        },

        stateCriteria() {
            if (!this.countryId) {
                return null;
            }

            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('countryId', this.countryId));
            return criteria;
        },
    },

    watch: {
        countryId: {
            immediate: true,
            handler(newId, oldId) {
                if (typeof oldId !== 'undefined') {
                    this.address.countryStateId = null;
                }

                if (this.countryId === null) {
                    this.country = null;
                    return Promise.resolve();
                }

                return this.countryRepository.get(this.countryId).then((country) => {
                    this.country = country;
                });
            },
        },

        'address.company'(newVal) {
            if (!newVal) {
                return;
            }

            this.customer.company = newVal;
        },
    },
});
