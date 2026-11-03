import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-customer-address-form',
    load: () => import('./src/sw-customer-address-form'),
    public: {
        props: [
            'customer',
            'address',
            'disabled',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_customer_address_form',
            'sw_customer_address_form_container',
            'sw_customer_address_form_company_field',
            'sw_customer_address_form_department_field',
            'sw_customer_address_form_salutation_title_container',
            'sw_customer_address_form_salutation_field',
            'sw_customer_address_form_title_field',
            'sw_customer_address_form_first_name_field',
            'sw_customer_address_form_last_name_field',
            'sw_customer_address_form_street_field',
            'sw_customer_address_form_additional_address_line_1_field',
            'sw_customer_address_form_additional_address_line_2_field',
            'sw_customer_address_form_zipcode_city_container',
            'sw_customer_address_form_zipcode_field',
            'sw_customer_address_form_city_field',
            'sw_customer_address_form_country_field',
            'sw_customer_address_form_state_field',
            'sw_customer_address_form_phone_number_field',
            'sw_customer_address_form_extension_slot',
        ],
        computed: [
            'addressRepository',
            'countryRepository',
            'countryStateRepository',
            'countryId',
            'countryCriteria',
            'stateCriteria',
            'salutationCriteria',
            'hasStates',
            'isBusinessAccountType',
        ],
        methods: [
            'setFieldRequired',
            'removeRequiredFieldError',
            'getCountryStates',
        ],
    },
});
