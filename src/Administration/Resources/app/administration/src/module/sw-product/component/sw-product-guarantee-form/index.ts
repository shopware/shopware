import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-product-guarantee-form',
    load: () => import('./src/sw-product-guarantee-form'),
    public: {
        props: [
            'allowEdit',
        ],
        blocks: [
            'sw_product_guarantee_form',
            'sw_product_guarantee_form_description',
            'sw_product_guarantee_form_content',
            'sw_product_guarantee_form_guarantee_months_field',
            'sw_product_guarantee_form_guarantee_confirmed_field',
            'sw_product_guarantee_form_terms',
            'sw_product_guarantee_form_guarantee_terms_media_field',
            'sw_product_guarantee_form_guarantee_terms_url_field',
            'sw_product_guarantee_form_requirements_notice',
        ],
        computed: [
            'product',
            'parentProduct',
            'guaranteeMonthsMinimum',
            'guaranteeMonthsMaximum',
            'guaranteeMonthsStep',
            'guaranteeMonthsValidationError',
            'guaranteeMonthsError',
            'effectiveGuaranteeMonths',
            'effectiveGuaranteeConfirmed',
            'effectiveManufacturerName',
            'effectiveManufacturerNumber',
            'unmetGuaranteeLabelRequirements',
        ],
        methods: [
            'onGuaranteeMonthsTyped',
            'commitGuaranteeMonths',
            'revealUnmetLabelRequirements',
        ],
    },
});
