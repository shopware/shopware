const { loadRegistry } = require('./registry/load-registry');
const { filterMigrations } = require('./registry/filter-migrations');

const { handleMtButton } = require('./no-deprecated-component-usage-checks/mt-button.check');
const { handleMtIcon } = require('./no-deprecated-component-usage-checks/mt-icon.check');
const { handleMtCard } = require('./no-deprecated-component-usage-checks/mt-card.check');
const { handleMtTextField } = require('./no-deprecated-component-usage-checks/mt-text-field.check');
const { handleMtSwitch } = require('./no-deprecated-component-usage-checks/mt-switch.check');
const { handleMtNumberField } = require('./no-deprecated-component-usage-checks/mt-number-field.check');
const { handleMtCheckbox } = require('./no-deprecated-component-usage-checks/mt-checkbox.check');
const { handleMtTabs } = require('./no-deprecated-component-usage-checks/mt-tabs.check');
const { handleMtSelect } = require('./no-deprecated-component-usage-checks/mt-select.check');
const { handleMtTextarea } = require('./no-deprecated-component-usage-checks/mt-textarea.check');
const { handleMtBanner } = require('./no-deprecated-component-usage-checks/mt-banner.check');
const { handleMtExternalLink } = require('./no-deprecated-component-usage-checks/mt-external-link.check');
const { handleMtDatepicker } = require('./no-deprecated-component-usage-checks/mt-datepicker.check');
const { handleMtColorpicker } = require('./no-deprecated-component-usage-checks/mt-colorpicker.check');
const { handleMtEmailField } = require('./no-deprecated-component-usage-checks/mt-email-field.check');
const { handleMtPasswordField } = require('./no-deprecated-component-usage-checks/mt-password-field.check');
const { handleMtUrlField } = require('./no-deprecated-component-usage-checks/mt-url-field.check');
const { handleMtProgressBar } = require('./no-deprecated-component-usage-checks/mt-progress-bar.check');
const { handleMtFloatingUi } = require('./no-deprecated-component-usage-checks/mt-floating-ui.check');
const { handleSwEntityListing } = require('./no-deprecated-component-usage-checks/sw-entity-listing.check');

const handlerMap = {
    'mt-button': handleMtButton,
    'mt-icon': handleMtIcon,
    'mt-card': handleMtCard,
    'mt-text-field': handleMtTextField,
    'mt-switch': handleMtSwitch,
    'mt-number-field': handleMtNumberField,
    'mt-checkbox': handleMtCheckbox,
    'mt-tabs': handleMtTabs,
    'mt-select': handleMtSelect,
    'mt-textarea': handleMtTextarea,
    'mt-banner': handleMtBanner,
    'mt-external-link': handleMtExternalLink,
    'mt-datepicker': handleMtDatepicker,
    'mt-colorpicker': handleMtColorpicker,
    'mt-email-field': handleMtEmailField,
    'mt-password-field': handleMtPasswordField,
    'mt-url-field': handleMtUrlField,
    'mt-progress-bar': handleMtProgressBar,
    'mt-floating-ui': handleMtFloatingUi,
    'sw-entity-listing': handleSwEntityListing,
};

function formatReferences(migration) {
    if (!migration.references?.length) {
        return '';
    }

    return migration.references.map((reference) => `${reference.type}: ${reference.target}`).join('\n');
}

function appendRegistryContext(message, migration) {
    const references = formatReferences(migration);

    return [
        message,
        '',
        migration.description,
        `Removed in Shopware ${migration.removedIn}.`,
        references ? `References:\n${references}` : '',
    ]
        .filter(Boolean)
        .join('\n');
}

function createRegistryContext(context, migration) {
    return {
        options: context.options,
        sourceCode: context.sourceCode,
        getSourceCode: () => context.getSourceCode(),
        report(descriptor) {
            if (!descriptor.message) {
                context.report(descriptor);
                return;
            }

            context.report({
                ...descriptor,
                message: appendRegistryContext(descriptor.message, migration),
            });
        },
    };
}

/* eslint-disable max-len */

/**
 * @sw-package framework
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
                enum: [
                    'disableFix',
                    'enableFix',
                ],
            },
        ],
    },
    /** @param {RuleContext} context */
    create(context) {
        const registry = loadRegistry();
        const handlers = filterMigrations(registry.componentApiMigrations)
            .filter((migration) => migration.handler)
            .map((migration) => {
                return {
                    component: migration.handler,
                    handler: handlerMap[migration.handler],
                    migration,
                };
            })
            .filter(({ handler }) => typeof handler === 'function');

        return context.sourceCode.parserServices.defineTemplateBodyVisitor(
            // Event handlers for <template> tags
            {
                VElement(node) {
                    handlers
                        .filter(({ component }) => component === node.name)
                        .forEach(({ handler, migration }) => handler(createRegistryContext(context, migration), node));
                },
            },
        );
    },
};
