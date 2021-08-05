import { string } from 'src/core/service/util.service';
import { PromotionPermissions } from 'src/module/sw-promotion/helper/promotion.helper';
import template from './sw-promotion-code-form.html.twig';
import './sw-promotion-code-form.scss';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 */
Component.register('sw-promotion-code-form', {
    template,

    inject: ['acl'],

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
    ],

    props: {
        promotion: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            modalIndividualVisible: false,
        };
    },

    computed: {

        isEditingDisabled() {
            if (!this.acl.can('promotion.editor')) {
                return true;
            }

            return !PromotionPermissions.isEditingAllowed(this.promotion);
        },

        // gets if the field is disabled.
        // this depends on the promotion setting
        // if codes should be used or not.
        isCodeFieldDisabled() {
            if (this.promotion.useIndividualCodes) {
                return true;
            }

            if (this.isEditingDisabled) {
                return true;
            }

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

            // if we use individual codes
            // the code can be empty
            if (this.promotion.useIndividualCodes) {
                return true;
            }

            // verify that our field has real data
            return !string.isEmptyOrSpaces(this.promotion.code);
        },
        repositoryIndividualCodes() {
            return this.repository;
        },
        // gets if the individual switch is enabled
        // this depends on the promotion "use codes" property.
        isSwitchIndividualDisabled() {
            if (this.isEditingDisabled) {
                return true;
            }
            return !this.promotion.useCodes;
        },
        isModalIndividualVisible() {
            return this.modalIndividualVisible;
        },
        codeHelpText() {
            // we do only want to show the help text
            // when individual codes are activated
            if (this.promotion.useCodes && this.promotion.useIndividualCodes) {
                return this.$tc('sw-promotion.detail.main.general.codes.helpTextIndividual');
            }

            return '';
        },

        ...mapPropertyErrors('promotion', ['code']),

    },
    methods: {
        openModalIndividualCodes() {
            const snippetRoot = 'sw-promotion.detail.main.general.codes';

            if (string.isEmptyOrSpaces(this.promotion.name)) {
                this.createNotificationWarning({
                    message: this.$tc(`${snippetRoot}.warningEmptyPromotionName`),
                });
                return;
            }

            this.modalIndividualVisible = true;
        },
        closeModalIndividualCodes() {
            this.modalIndividualVisible = false;
        },
    },
});
