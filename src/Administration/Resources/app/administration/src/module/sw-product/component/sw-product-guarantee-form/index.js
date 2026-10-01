/*
 * @sw-package inventory
 */

import template from './sw-product-guarantee-form.html.twig';
import './sw-product-guarantee-form.scss';

const { mapPropertyErrors, mapState } = Shopware.Component.getComponentHelper();

const GUARANTEE_MONTHS_MINIMUM = 30;
const GUARANTEE_MONTHS_MAXIMUM = 600;
const GUARANTEE_MONTHS_STEP = 6;

function isValidGuaranteeDuration(guaranteeMonths) {
    return (
        Number.isInteger(guaranteeMonths) &&
        guaranteeMonths >= GUARANTEE_MONTHS_MINIMUM &&
        guaranteeMonths <= GUARANTEE_MONTHS_MAXIMUM &&
        guaranteeMonths % GUARANTEE_MONTHS_STEP === 0
    );
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
        ]),

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
                    this.$tc('sw-product.settingsForm.noticeGuaranteeRequirementMonths', 0, {
                        label: this.$tc('sw-product.settingsForm.labelGuaranteeMonths'),
                        minimum: GUARANTEE_MONTHS_MINIMUM,
                        maximum: GUARANTEE_MONTHS_MAXIMUM,
                        step: GUARANTEE_MONTHS_STEP,
                    }),
                );
            }

            if (this.effectiveManufacturerName === '') {
                requirements.push(
                    this.$tc('sw-product.settingsForm.noticeGuaranteeRequirementManufacturer', 0, {
                        label: this.$tc('sw-product.basicForm.labelManufacturer'),
                        card: this.$tc('sw-product.detailBase.cardTitleProductInfo'),
                    }),
                );
            }

            if (this.effectiveManufacturerNumber === '') {
                requirements.push(
                    this.$tc('sw-product.settingsForm.noticeGuaranteeRequirementManufacturerNumber', 0, {
                        label: this.$tc('sw-product.settingsForm.labelManufacturerNumber'),
                        card: this.$tc('sw-product.detailBase.cardTitleSettings'),
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
