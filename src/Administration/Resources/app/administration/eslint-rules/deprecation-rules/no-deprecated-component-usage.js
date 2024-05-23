const fs = require('fs');
const path = require('path');

const { handleMtButton } = require('./no-deprecated-component-usage-checks/mt-button.check');
const { handleMtIcon } = require('./no-deprecated-component-usage-checks/mt-icon.check')
const { handleMtCard } = require("./no-deprecated-component-usage-checks/mt-card.check");
const { handleMtTextField } = require("./no-deprecated-component-usage-checks/mt-text-field.check");
const { handleMtSwitch } = require("./no-deprecated-component-usage-checks/mt-switch.check");
const { handleMtNumberField } = require("./no-deprecated-component-usage-checks/mt-number-field.check");
const { handleMtCheckbox } = require("./no-deprecated-component-usage-checks/mt-checkbox.check");
const { handleMtTabs } = require("./no-deprecated-component-usage-checks/mt-tabs.check");
const { handleMtSelect } = require("./no-deprecated-component-usage-checks/mt-select.check");
const { handleMtTextarea } = require("./no-deprecated-component-usage-checks/mt-textarea.check");
const { handleMtBanner } = require("./no-deprecated-component-usage-checks/mt-banner.check");
const { handleMtExternalLink } = require("./no-deprecated-component-usage-checks/mt-external-link.check");
const { handleMtDatepicker } = require("./no-deprecated-component-usage-checks/mt-datepicker.check");
const { handleMtColorpicker } = require("./no-deprecated-component-usage-checks/mt-colorpicker.check");
const { handleMtEmailField } = require("./no-deprecated-component-usage-checks/mt-email-field.check");
const { handleMtPasswordField } = require("./no-deprecated-component-usage-checks/mt-password-field.check");
const { handleMtUrlField } = require("./no-deprecated-component-usage-checks/mt-url-field.check");
const { handleMtProgressBar } = require("./no-deprecated-component-usage-checks/mt-progress-bar.check");
const { handleMtFloatingUi } = require("./no-deprecated-component-usage-checks/mt-floating-ui.check");

/* eslint-disable max-len */

/**
 * @package admin
 *
 * This rule checks if converted components still use the old logic, props, etc.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = {
    meta: {
        type: 'problem',
        fixable: 'code',
        docs: {
            description: 'No usage of deprecated components',
            recommended: true,
        },
        schema: [
            {
                enum: ['disableFix', 'enableFix'],
            }
        ]
    },
    /** @param {RuleContext} context */
    create(context) {
        return context.parserServices.defineTemplateBodyVisitor(
            // Event handlers for <template> tags
            {
                VElement(node) {
                    // Handle mt-button component
                    handleMtButton(context, node);
                    // Handle mt-icon component
                    handleMtIcon(context, node);
                    // Handle mt-card component
                    handleMtCard(context, node);
                    // Handle mt-text-field component
                    handleMtTextField(context, node);
                    // Handle mt-switch-field component
                    handleMtSwitch(context, node);
                    // Handle mt-number-field component
                    handleMtNumberField(context, node);
                    // Handle mt-checkbox
                    handleMtCheckbox(context, node);
                    // Handle mt-tabs
                    handleMtTabs(context, node);
                    // Handle mt-select
                    handleMtSelect(context, node);
                    // Handle mt-textarea
                    handleMtTextarea(context, node);
                    // Handle mt-banner
                    handleMtBanner(context, node);
                    // Handle mt-external-link
                    handleMtExternalLink(context, node);
                    // Handle mt-datepicker
                    handleMtDatepicker(context, node);
                    // Handle mt-colorpicker
                    handleMtColorpicker(context, node);
                    // Handle mt-email-field component
                    handleMtEmailField(context, node);
                    // Handle mt-password-field
                    handleMtPasswordField(context, node);
                    // Handle mt-url-field
                    handleMtUrlField(context, node);
                    // Handle mt-progress-bar
                    handleMtProgressBar(context, node);
                    // Handle mt-floating-ui
                    handleMtFloatingUi(context, node);
                },
            }
        )
    }
};
