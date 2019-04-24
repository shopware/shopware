import { Component, Mixin } from 'src/core/shopware';
import template from './sw-promotion-code-form.html.twig';

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
        }
    },
    methods: {
        isEmptyOrSpaces(str) {
            return str === null || str.match(/^ *$/) !== null;
        }
    }
});
