/*
 * @sw-package inventory
 */

import template from './sw-product-guarantee-form.html.twig';
import './sw-product-guarantee-form.scss';

const { mapPropertyErrors } = Shopware.Component.getComponentHelper();
const { ShopwareError } = Shopware.Classes;

const GUARANTEE_MONTHS_MINIMUM = 30;
const GUARANTEE_MONTHS_MAXIMUM = 600;
const GUARANTEE_MONTHS_STEP = 6;
const GUARANTEE_MONTHS_ERROR_CODE = 'INVALID_GARAN_GUARANTEE_MONTHS';

function isValidGuaranteeDuration(guaranteeMonths) {
    return (
        Number.isInteger(guaranteeMonths) &&
        guaranteeMonths >= GUARANTEE_MONTHS_MINIMUM &&
        guaranteeMonths <= GUARANTEE_MONTHS_MAXIMUM &&
        guaranteeMonths % GUARANTEE_MONTHS_STEP === 0
    );
}

function clampGuaranteeDuration(guaranteeMonths) {
    return Math.min(Math.max(guaranteeMonths, GUARANTEE_MONTHS_MINIMUM), GUARANTEE_MONTHS_MAXIMUM);
}

// Both boundaries sit on the half-year grid, so clamping before rounding stays on the grid.
function nearestValidGuaranteeDuration(guaranteeMonths) {
    return Math.round(clampGuaranteeDuration(guaranteeMonths) / GUARANTEE_MONTHS_STEP) * GUARANTEE_MONTHS_STEP;
}

// The field steps from the value it holds, so an off-grid duration would only ever reach the
// next off-grid one. Step from the grid around the previous value instead.
function steppedValidGuaranteeDuration(guaranteeMonths, previousGuaranteeMonths) {
    if (guaranteeMonths === null || guaranteeMonths === undefined) {
        return null;
    }

    // No direction to read: an empty field, or a boundary the field already clamped the step to.
    if (!Number.isFinite(previousGuaranteeMonths) || guaranteeMonths === previousGuaranteeMonths) {
        return nearestValidGuaranteeDuration(guaranteeMonths);
    }

    const stepped =
        guaranteeMonths > previousGuaranteeMonths
            ? Math.floor(previousGuaranteeMonths / GUARANTEE_MONTHS_STEP) * GUARANTEE_MONTHS_STEP + GUARANTEE_MONTHS_STEP
            : Math.ceil(previousGuaranteeMonths / GUARANTEE_MONTHS_STEP) * GUARANTEE_MONTHS_STEP - GUARANTEE_MONTHS_STEP;

    return clampGuaranteeDuration(stepped);
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

    data() {
        return {
            guaranteeMonthsWasTyped: false,
        };
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

        guaranteeMonthsMaximum() {
            return GUARANTEE_MONTHS_MAXIMUM;
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
                        maximum: GUARANTEE_MONTHS_MAXIMUM,
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

    mounted() {
        Shopware.Utils.EventBus.on('sw-product-detail-save-success', this.revealUnmetLabelRequirements);
    },

    beforeUnmount() {
        Shopware.Utils.EventBus.off('sw-product-detail-save-success', this.revealUnmetLabelRequirements);
    },

    methods: {
        // mt-number-field emits `change` for typed input only, never for its stepper.
        onGuaranteeMonthsTyped() {
            this.guaranteeMonthsWasTyped = true;
        },

        /**
         * A typed duration is kept as typed: a guarantee duration is a commercial claim, so the
         * merchant should see the error rather than have their 44 silently become 42.
         */
        commitGuaranteeMonths(guaranteeMonths, previousGuaranteeMonths) {
            const wasTyped = this.guaranteeMonthsWasTyped;
            this.guaranteeMonthsWasTyped = false;

            if (wasTyped) {
                return guaranteeMonths;
            }

            return steppedValidGuaranteeDuration(guaranteeMonths, previousGuaranteeMonths);
        },

        /**
         * A product saves fine while the label is switched on but incomplete, so nothing pulls the
         * merchant away from wherever they were. Bring them to the notice that tells them the label
         * stays hidden.
         */
        revealUnmetLabelRequirements() {
            if (this.unmetGuaranteeLabelRequirements.length === 0) {
                return;
            }

            this.$nextTick(() => {
                this.$refs.requirementsNotice?.$el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        },
    },
};
