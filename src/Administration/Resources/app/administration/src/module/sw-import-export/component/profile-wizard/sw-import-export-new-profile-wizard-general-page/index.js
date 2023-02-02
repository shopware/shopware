/**
 * @package system-settings
 */
import string from 'src/core/service/utils/string.utils';
import template from './sw-import-export-new-profile-wizard-general-page.html.twig';
import './sw-import-export-new-profile-wizard-general-page.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        profile: {
            type: Object,
            required: true,
        },
    },

    computed: {
        inputValid() {
            return this.isFieldFilled(this.profile.sourceEntity) &&
                this.isFieldFilled(this.profile.type) &&
                this.isFieldFilled(this.profile.label);
        },
    },

    watch: {
        inputValid: {
            immediate: true,
            handler(isValid) {
                if (isValid) {
                    this.$emit('next-allow');
                    return;
                }

                this.$emit('next-disable');
            },
        },
    },

    methods: {
        isFieldFilled(field) {
            return !!field || !string.isEmptyOrSpaces(field);
        },
    },
};
