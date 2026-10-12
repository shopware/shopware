/**
 * @sw-package framework
 */
import template from './sw-custom-field-type-base.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl'],

    props: {
        currentCustomField: {
            type: Object,
            required: true,
        },
        set: {
            type: Object,
            required: true,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            propertyNames: {
                label: this.$t('sw-settings-custom-field.customField.detail.labelLabel'),
            },
        };
    },

    computed: {
        locales() {
            if (this.set.config.hasOwnProperty('translated') && this.set.config.translated === true) {
                // Only full locale codes (e.g. en-GB, de-DE) represent real admin languages.
                // vue-i18n also registers short aliases (en, de) that must not become editable tabs.
                return Object.keys(this.$root.$i18n.messages.value).filter((locale) => locale.includes('-'));
            }

            return [this.$root.$i18n.fallbackLocale.value];
        },
    },
};
