import template from './sw-promotion-v2-detail-base.html.twig';

const { Mixin } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
        'promotionCodeApiService',
        'customFieldDataProviderService',
    ],

    mixins: [
        Mixin.getByName('placeholder'),
    ],

    props: {
        promotion: {
            type: Object,
            required: false,
            default() {
                return null;
            },
        },

        isLoading: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default() {
                return false;
            },
        },

        isCreateMode: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            selectedCodeType: '0',
            isGenerating: false,
            isGenerateSuccessful: false,
            codeSortProperty: 'code',
            codeSortDirection: 'ASC',
            CODE_TYPES: Object.freeze({
                NONE: '0',
                FIXED: '1',
                INDIVIDUAL: '2',
            }),
            customFieldSets: null,
        };
    },

    computed: {
        codeTypeOptions() {
            return Object.entries(this.CODE_TYPES).map(type => Object.create({
                label: this.$tc(`sw-promotion-v2.detail.base.codes.${type[0].toLowerCase()}.description`),
                value: type[1],
            }));
        },

        ...mapPropertyErrors('promotion', ['name', 'validUntil']),

        showCustomFields() {
            return this.customFieldSets && this.customFieldSets.length > 0;
        },
    },

    watch: {
        promotion() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.promotion) {
                return;
            }

            this.loadCustomFieldSets();

            if (this.promotion.useCodes && this.promotion.useIndividualCodes) {
                this.setNewCodeType(this.CODE_TYPES.INDIVIDUAL);
                this.initialSort();

                return;
            }

            this.setNewCodeType(this.promotion.useCodes ? this.CODE_TYPES.FIXED : this.CODE_TYPES.NONE);
        },

        initialSort() {
            this.promotion.individualCodes.sort((a, b) => {
                const aValue = a[this.codeSortProperty];
                const bValue = b[this.codeSortProperty];

                let isBigger = null;

                if (typeof aValue === 'string' && typeof bValue === 'string') {
                    isBigger = aValue.toUpperCase() > bValue.toUpperCase();
                }

                if (typeof aValue === 'number' && typeof bValue === 'number') {
                    isBigger = (aValue - bValue) > 0;
                }

                if (isBigger !== null) {
                    if (this.codeSortDirection === 'DESC') {
                        return isBigger ? -1 : 1;
                    }

                    return isBigger ? 1 : -1;
                }

                return 0;
            });
        },

        onChangeCodeType(value) {
            const hasInactiveIndividualCodes = value !== this.CODE_TYPES.INDIVIDUAL &&
                (this.promotion.individualCodes !== null && this.promotion.individualCodes.length > 0);
            const hasInactiveFixedCode = value !== this.CODE_TYPES.FIXED &&
                (this.promotion.code !== null && this.promotion.code.length > 0);

            this.$emit('clean-up-codes', hasInactiveIndividualCodes, hasInactiveFixedCode);
            this.setNewCodeType(value);
        },

        setNewCodeType(value) {
            this.promotion.useCodes = value !== this.CODE_TYPES.NONE;
            this.promotion.useIndividualCodes = value === this.CODE_TYPES.INDIVIDUAL;

            this.selectedCodeType = value;
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('promotion').then((sets) => {
                this.customFieldSets = sets;
            });
        },

        onGenerateCodeFixed() {
            this.isGenerating = true;
            this.promotionCodeApiService.generateCodeFixed().then((code) => {
                this.promotion.code = code;
                this.isGenerateSuccessful = true;
            }).finally(() => {
                this.isGenerating = false;
            });
        },

        generateFinish() {
            this.isGenerateSuccessful = false;
        },
    },
};
