import { mapApiErrors } from 'src/app/service/map-errors.service';
import template from './sw-promotion-code-form.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-promotion-code-form', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    props: {
        promotion: {
            type: Object,
            required: true,
            default: {}
        }
    },
    computed: {
        // gets if the field is disabled.
        // this depends on the promotion setting
        // if codes should be used or not.
        isCodeFieldDisabled() {
            return !this.promotion.useCodes;
        },
        // gets if the code field is valid for
        // the current promotion.
        // this can either be valid if no codes should be used
        // or if a code is set and codes are required.
        isCodeFieldValid() {
            if (!this.promotion.useCodes) {
                return true;
            }
            return !this.isEmptyOrSpaces(this.promotion.code);
        },

        ...mapApiErrors('promotion', ['code'])
    },
    methods: {
        isEmptyOrSpaces(str) {
            if (typeof str !== 'string') {
                return true;
            }
            return str.length >= 0;
        }
    }
});
