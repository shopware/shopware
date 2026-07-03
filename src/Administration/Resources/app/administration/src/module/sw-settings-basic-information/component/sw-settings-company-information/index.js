/**
 * @sw-package fundamentals@framework
 */
import template from './sw-settings-company-information.html.twig';
import './sw-settings-company-information.scss';

function createEmptyCompanyInfo() {
    return {
        logoId: null,
        companyName: '',
        companyEmail: '',
        companyPhone: '',
        companyStreet: '',
        companyCountryId: null,
        companyZipcode: '',
        companyCity: '',
        companyUrl: '',
        taxNumber: '',
        taxOffice: '',
        vatId: '',
        bankName: '',
        bankIban: '',
        bankBic: '',
        placeOfJurisdiction: '',
        placeOfFulfillment: '',
        executiveDirector: '',
    };
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: ['update:value'],

    props: {
        value: {
            type: Object,
            required: false,
            default: null,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            defaultValue: createEmptyCompanyInfo(),
        };
    },

    computed: {
        currentValue: {
            get() {
                return this.value ?? this.defaultValue;
            },

            set(val) {
                this.$emit('update:value', val);
            },
        },
    },

    watch: {
        currentValue: {
            deep: true,
            handler(value) {
                this.$emit('update:value', value);
            },
        },
    },

    methods: {
        onChangeCompanyLogo(media) {
            this.currentValue.logoId = media.at(0)?.id || null;
        },
    },
};
