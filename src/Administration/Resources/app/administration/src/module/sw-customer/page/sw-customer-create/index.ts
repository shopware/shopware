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
    name: 'sw-customer-create',
    load: () => import('./src/sw-customer-create'),
    public: {
        blocks: [
            'sw_customer_create',
            'sw_customer_create_header',
            'sw_customer_create_language_switch',
            'sw_customer_create_actions',
            'sw_customer_create_content',
            'sw_customer_create_base_form',
            'sw_customer_create_adress_form',
        ],
        computed: [
            'customerRepository',
            'validCompanyField',
            'languageRepository',
            'languageCriteria',
            'languageId',
            'salutationRepository',
            'salutationCriteria',
            'salutationFilter',
        ],
        methods: [
            'createdComponent',
            'saveFinish',
            'validateEmail',
            'onSave',
            'onChangeSalesChannel',
            'createErrorMessageForCompanyField',
            'loadLanguage',
            'getDefaultSalutation',
        ],
    },
});
