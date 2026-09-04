/*
 * @sw-package inventory
 */

import template from './sw-product-guarantee-form.html.twig';
import './sw-product-guarantee-form.scss';

const { mapPropertyErrors } = Shopware.Component.getComponentHelper();
const { ShopwareError } = Shopware.Classes;

const GUARANTEE_MONTHS_MINIMUM = 30;
const GUARANTEE_MONTHS_STEP = 6;
const GUARANTEE_MONTHS_ERROR_CODE = 'INVALID_GARAN_GUARANTEE_MONTHS';

function isValidGuaranteeDuration(guaranteeMonths) {
    return (
        Number.isInteger(guaranteeMonths) &&
        guaranteeMonths >= GUARANTEE_MONTHS_MINIMUM &&
        guaranteeMonths % GUARANTEE_MONTHS_STEP === 0
    );
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    computed: {
        product() {
            return Shopware.Store.get('swProductDetail').product;
        },

        parentProduct() {
            return Shopware.Store.get('swProductDetail').parentProduct;
        },

        guaranteeMonthsMinimum() {
            return GUARANTEE_MONTHS_MINIMUM;
        },

        guaranteeMonthsStep() {
            return GUARANTEE_MONTHS_STEP;
        },

        guaranteeMonthsValidationError() {
            const guaranteeMonths = this.product.guaranteeMonths;

            if (guaranteeMonths === null || guaranteeMonths === undefined) {
                return null;
            }

            if (isValidGuaranteeDuration(guaranteeMonths)) {
                return null;
            }

            return new ShopwareError({ code: GUARANTEE_MONTHS_ERROR_CODE });
        },

        guaranteeMonthsError() {
            const apiError = this.productGuaranteeMonthsError ?? null;

            if (apiError !== null && apiError.code !== GUARANTEE_MONTHS_ERROR_CODE) {
                return apiError;
            }

            return this.guaranteeMonthsValidationError;
        },

        effectiveGuaranteeMonths() {
            return this.product.guaranteeMonths ?? this.parentProduct.guaranteeMonths ?? null;
        },

        effectiveGuaranteeConfirmed() {
            return this.product.guaranteeConfirmed ?? this.parentProduct.guaranteeConfirmed ?? false;
        },

        effectiveManufacturerName() {
            const manufacturer = this.product.manufacturer ?? this.parentProduct.manufacturer ?? null;

            return (manufacturer?.translated?.name ?? manufacturer?.name ?? '').trim();
        },

        effectiveManufacturerNumber() {
            return (this.product.manufacturerNumber ?? this.parentProduct.manufacturerNumber ?? '').trim();
        },

        unmetGuaranteeLabelRequirements() {
            if (!this.effectiveGuaranteeConfirmed) {
                return [];
            }

            const requirements = [];

            if (!isValidGuaranteeDuration(this.effectiveGuaranteeMonths)) {
                requirements.push(
                    this.$t('sw-product.settingsForm.noticeGuaranteeRequirementMonths', {
                        label: this.$t('sw-product.settingsForm.labelGuaranteeMonths'),
                        minimum: GUARANTEE_MONTHS_MINIMUM,
                        step: GUARANTEE_MONTHS_STEP,
                    }),
                );
            }

            if (this.effectiveManufacturerName === '') {
                requirements.push(
                    this.$t('sw-product.settingsForm.noticeGuaranteeRequirementManufacturer', {
                        label: this.$t('sw-product.basicForm.labelManufacturer'),
                        card: this.$t('sw-product.detailBase.cardTitleProductInfo'),
                    }),
                );
            }

            if (this.effectiveManufacturerNumber === '') {
                requirements.push(
                    this.$t('sw-product.settingsForm.noticeGuaranteeRequirementManufacturerNumber', {
                        label: this.$t('sw-product.settingsForm.labelManufacturerNumber'),
                        card: this.$t('sw-product.detailBase.cardTitleSettings'),
                    }),
                );
            }

            return requirements;
        },

        ...mapPropertyErrors('product', [
            'guaranteeMonths',
            'guaranteeConfirmed',
        ]),
    },
};
