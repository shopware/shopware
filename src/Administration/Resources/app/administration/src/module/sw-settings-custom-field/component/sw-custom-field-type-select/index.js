/**
 * @package system-settings
 */
import template from './sw-custom-field-type-select.html.twig';
import './sw-custom-field-type-select.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    data() {
        return {
            multiSelectSwitch: false,
            propertyNames: {
                label: this.$tc('sw-settings-custom-field.customField.detail.labelLabel'),
                placeholder: this.$tc('sw-settings-custom-field.customField.detail.labelPlaceholder'),
                helpText: this.$tc('sw-settings-custom-field.customField.detail.labelHelpText'),
            },
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.currentCustomField.config.hasOwnProperty('options')) {
                this.$set(this.currentCustomField.config, 'options', []);
                this.addOption();
                this.addOption();
            }

            if (!this.currentCustomField.config.hasOwnProperty('componentName')) {
                this.currentCustomField.config.componentName = 'sw-single-select';
            }

            this.$set(this.currentCustomField.config, 'options', this.currentCustomField.config.options.map(option => {
                if (Array.isArray(option.label)) {
                    option.label = {};
                }

                return option;
            }));

            this.multiSelectSwitch = this.currentCustomField.config.componentName === 'sw-multi-select';
        },
        addOption() {
            this.currentCustomField.config.options.push({ value: '', label: {} });
        },
        onClickAddOption() {
            this.addOption();
        },
        getLabel(locale) {
            const snippet = this.$tc('sw-settings-custom-field.customField.detail.labelLabel');
            const language = this.$tc(`locale.${locale}`);

            return `${snippet} (${language})`;
        },
        onDeleteOption(index) {
            this.currentCustomField.config.options.splice(index, 1);
        },
        onChangeMultiSelectSwitch(state) {
            if (state) {
                this.currentCustomField.config.componentName = 'sw-multi-select';
                return;
            }

            this.currentCustomField.config.componentName = 'sw-single-select';
        },
    },
};
