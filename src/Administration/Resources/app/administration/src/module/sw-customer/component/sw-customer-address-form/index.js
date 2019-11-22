import template from './sw-customer-address-form.html.twig';

const { Component } = Shopware;
const { mapApiErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-customer-address-form', {
    template,

    inject: ['repositoryFactory'],

    props: {
        customer: {
            type: Object,
            required: true
        },

        address: {
            type: Object,
            required: true,
            default() {
                return this.addressRepository.create(this.context);
            }
        }
    },

    computed: {
        addressRepository() {
            return this.repositoryFactory.create(
                this.customer.addresses.entity,
                this.customer.addresses.source
            );
        },

        ...mapApiErrors('address', [
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
            'vatId'
        ]),

        ...mapApiErrors('address', ['countryId', 'salutationId', 'city', 'street', 'zipcode', 'lastName', 'firstName'])
    }
});
