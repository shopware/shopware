/**
 * @package system-settings
 */
import template from './sw-country-state-detail.html.twig';

const { Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl'],

    mixins: [
        Mixin.getByName('placeholder'),
    ],

    props: {
        countryState: {
            type: Object,
            required: true,
        },
    },

    computed: {
        modalTitle() {
            if (this.countryState.isNew()) {
                return this.$tc('sw-country-state-detail.titleNew');
            }

            return this.$tc('sw-country-state-detail.titleEdit');
        },

        tooltipSave() {
            if (!this.acl.can('country.editor')) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('country.editor'),
                    showOnDisabledElements: true,
                };
            }

            return {
                message: '',
                disabled: true,
            };
        },
    },

    methods: {
        onCancel() {
            this.$emit('attribute-edit-cancel', this.countryState);
        },
        onSave() {
            this.$emit('attribute-edit-save', this.countryState);
        },
    },
};
