/**
 * @sw-package framework
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    data() {
        return {
            propertyNames: {
                label: this.$t('sw-settings-custom-field.customField.detail.labelLabel'),
                placeholder: this.$t('sw-settings-custom-field.customField.detail.labelPlaceholder'),
                helpText: this.$t('sw-settings-custom-field.customField.detail.labelHelpText'),
            },
        };
    },
};
