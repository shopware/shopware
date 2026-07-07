import template from './sw-settings-company-information.html.twig';
import './sw-settings-company-information.scss';

/**
 * @private
 * @sw-package fundamentals@framework
 */
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
            defaultValue: {
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
            },
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
