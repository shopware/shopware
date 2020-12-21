import template from './sw-promotion-v2-detail-base.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-promotion-v2-detail-base', {
    template,

    inject: [
        'acl',
        'promotionCodeApiService'
    ],

    mixins: [
        'placeholder'
    ],

    props: {
        promotion: {
            type: Object,
            required: false,
            default: null
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false
        },

        isCreateMode: {
            type: Boolean,
            required: true
        }
    },

    data() {
        return {
            selectedCodeType: '0',
            isGenerating: false,
            isGenerateSuccessful: false,
            CODE_TYPES: Object.freeze({
                NONE: '0',
                FIXED: '1',
                INDIVIDUAL: '2'
            })
        };
    },

    computed: {
        codeTypeOptions() {
            return Object.entries(this.CODE_TYPES).map(type => Object.create({
                label: this.$tc(`sw-promotion-v2.detail.base.codes.${type[0].toLowerCase()}.description`),
                value: type[1]
            }));
        },

        ...mapPropertyErrors('promotion', ['name', 'validUntil'])
    },

    watch: {
        promotion() {
            this.createdComponent();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.promotion) {
                return;
            }

            if (this.promotion.useCodes && this.promotion.useIndividualCodes) {
                this.setNewCodeType(this.CODE_TYPES.INDIVIDUAL);

                return;
            }

            const newCode = typeof this.promotion.useCodes !== 'string' ? '0' : Number(this.promotion.useCodes).toString();
            this.setNewCodeType(newCode);
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
        }
    }
});
