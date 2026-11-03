/**
 * @sw-package fundamentals@after-sales
 */
import template from './sw-import-export-new-profile-wizard-general-page.html.twig';
import { string } from '../dependencies';

/**
 * @public
 */
export default {
    template,

    emits: ['next-allow', 'next-disable'],

    props: {
        profile: {
            type: Object,
            required: true,
        },
    },

    computed: {
        inputValid() {
            return this.isFieldFilled(this.profile.sourceEntity) && this.isFieldFilled(this.profile.type);
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
