/**
 * @package services-settings
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    data() {
        return {
            propertyNames: {
                label: this.$tc('sw-settings-custom-field.customField.detail.labelLabel'),
                helpText: this.$tc('sw-settings-custom-field.customField.detail.labelHelpText'),
            },
        };
    },
};
