import template from './sw-promotion-v2-detail-base.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-promotion-v2-detail-base', {
    template,

    inject: [
        'repositoryFactory',
        'acl'
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
                label: this.$tc(`sw-promotion-v2.detail.codes.${type[0].toLowerCase()}.description`),
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
                this.selectedCodeType = this.CODE_TYPES.INDIVIDUAL;

                return;
            }

            this.selectedCodeType = Number(this.promotion.useCodes).toString();
        },

        onChangeCodeType(value) {
            this.promotion.useCodes = value !== this.CODE_TYPES.NONE;
            this.promotion.useIndividualCodes = value === this.CODE_TYPES.INDIVIDUAL;
        }
    }
});
