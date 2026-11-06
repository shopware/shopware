/**
 * @sw-package framework
 *
 * Shopware 6.8 removes the `dateFilter` and `assetFilter` computeds of many Administration
 * components. In the script of an override or extension of such a component the codemod
 * rewrites `this.dateFilter` and `this.$super('dateFilter')` to `Shopware.Filter.getByName('date')`.
 * A template cannot reach `Shopware`, so a template that still calls the filter gets a TODO
 * comment, unless the override next to it defines the computed again.
 */

const fs = require('fs');
const path = require('path');
const { createTodoReporter, todoMessages } = require('./utils/todo-comment');
const {
    componentContext,
    componentOptions,
    definesMember,
    isSuperCallOf,
    propertyName,
} = require('./utils/component-context');
const { mustacheMatches, referencePattern } = require('./utils/twig-mustache');

const DATE_FILTER_COMPONENTS = [
    'sw-advanced-selection-rule',
    'sw-media-folder-item',
    'sw-media-media-item',
    'sw-settings-logging-list',
    'sw-extension-card-base',
    'sw-self-maintained-extension-card',
    'sw-import-export-activity',
    'sw-import-export-activity-result-modal',
    'sw-import-export-activity-log-info-modal',
    'sw-cms-list',
    'sw-cms-layout-modal',
    'sw-review-detail',
    'sw-review-list',
    'sw-promotion-v2-individual-codes-behavior',
    'sw-settings-rule-list',
    'sw-product-list',
    'sw-product-detail-reviews',
    'sw-settings-snippet-set-list',
    'sw-product-stream-list',
    'sw-order-list',
    'sw-order-send-document-modal',
    'sw-order-state-history-card-entry',
    'sw-order-document-card',
    'sw-order-general-info',
    'sw-dashboard-statistics',
    'sw-sales-channel-list',
    'sw-sales-channel-detail-base',
    'sw-sales-channel-create-base',
    'sw-newsletter-recipient-list',
];

const ASSET_FILTER_COMPONENTS = [
    'sw-cms-layout-assignment-modal',
    'sw-flow-list',
    'sw-mail-header-footer-list',
    'sw-mail-template-list',
    'sw-order-customer-grid',
    'sw-product-detail-context-prices',
    'sw-product-detail-cross-selling',
    'sw-promotion-v2-individual-codes-behavior',
    'sw-sales-channel-products-assignment-dynamic-product-groups',
    'sw-settings-listing-option-criteria-grid',
    'sw-settings-listing',
    'sw-settings-product-feature-sets-values-card',
    'sw-tax-rule-card',
];

const MEMBER_SECTIONS = ['computed', 'methods', 'data'];

/**
 * Whether the script next to a template defines the computed again, e.g. `sw-order-list/index.js`.
 *
 * @param {string} filename
 * @param {string} computed
 */
function siblingDefines(filename, computed) {
    const definition = new RegExp(`\\b${computed}\\s*(\\(\\s*\\)\\s*\\{|:)`);

    return ['index.js', 'index.ts'].some((candidate) => {
        try {
            return definition.test(fs.readFileSync(path.join(path.dirname(filename), candidate), 'utf8'));
        } catch {
            return false;
        }
    });
}

/**
 * @param {{ computed: string, filter: string, components: string[] }} spec
 * @returns {import('eslint').Rule.RuleModule}
 */
function createFilterComputedRule({ computed, filter, components }) {
    const replacement = `Shopware.Filter.getByName('${filter}')`;

    return {
        meta: {
            type: 'problem',
            docs: {
                description: `Replace the \`${computed}\` computed that Shopware 6.8 removes with \`${replacement}\``,
                category: 'Shopware 6.8 Migration',
                recommended: false,
            },
            fixable: 'code',
            schema: [],
            messages: {
                ...todoMessages,
                replace: `"{{ component }}" no longer has the "${computed}" computed. Use "${replacement}" instead.`,
            },
        },
        create(context) {
            const reporter = createTodoReporter(context);
            const filename = context.physicalFilename ?? context.filename;
            const affectedComponent = (node) => componentContext(context, node).find((name) => components.includes(name));
            const scriptTodo = (component) => `"${component}" no longer has the "${computed}" computed. Use ${replacement} instead.`;

            /**
             * The component whose removed computed `node` reads, or null when the override defines its own.
             *
             * @param {import('eslint').Rule.Node} node
             */
            const removedFrom = (node) => {
                const component = affectedComponent(node);

                if (!component || definesMember(componentOptions(node), MEMBER_SECTIONS, computed)) {
                    return null;
                }

                return component;
            };

            const replace = (node, component) => {
                context.report({
                    node,
                    messageId: 'replace',
                    data: { component },
                    fix: (fixer) => fixer.replaceText(node, replacement),
                });
            };

            const scriptVisitor = {
                MemberExpression(node) {
                    if (node.object.type !== 'ThisExpression' || propertyName(node) !== computed) {
                        return;
                    }

                    const component = removedFrom(node);
                    const isAssignment = node.parent.type === 'AssignmentExpression' && node.parent.left === node;

                    if (!component || isAssignment) {
                        return;
                    }

                    if (node.computed) {
                        reporter.reportScript(node, scriptTodo(component));

                        return;
                    }

                    replace(node, component);
                },

                // Also in an override that defines the computed again: the parent no longer has it.
                CallExpression(node) {
                    const component = isSuperCallOf(node, [computed]) ? affectedComponent(node) : null;

                    if (component) {
                        replace(node, component);
                    }
                },

                VariableDeclarator(node) {
                    if (node.id.type !== 'ObjectPattern' || node.init?.type !== 'ThisExpression') {
                        return;
                    }

                    const destructured = node.id.properties.some((property) => {
                        return property.type === 'Property' && propertyName(property) === computed;
                    });
                    const component = destructured ? removedFrom(node) : null;

                    if (component) {
                        reporter.reportScript(node, scriptTodo(component));
                    }
                },
            };

            const parserServices = context.sourceCode.parserServices;

            if (!parserServices?.defineTemplateBodyVisitor) {
                return scriptVisitor;
            }

            const reportTemplate = (element) => {
                const component = affectedComponent(null);

                if (!element || !component || siblingDefines(filename, computed)) {
                    return;
                }

                reporter.reportTemplate(
                    element,
                    `"${component}" no longer has the "${computed}" computed. ` +
                        `Add a "${computed}" computed that returns ${replacement} to your override.`,
                );
            };

            const templateVisitor = {
                Identifier(node) {
                    const parent = node.parent;
                    const isMemberName = parent.type === 'MemberExpression' && parent.property === node && !parent.computed;

                    if (node.name !== computed || isMemberName) {
                        return;
                    }

                    let element = parent;

                    while (element && element.type !== 'VElement') {
                        element = element.parent;
                    }

                    reportTemplate(element);
                },

                // `{{ … }}` in `.html.twig` files, which the Vue parser keeps as text.
                VText(node) {
                    if (mustacheMatches(context.sourceCode, node, referencePattern([computed])).length > 0) {
                        reportTemplate(node.parent);
                    }
                },
            };

            return parserServices.defineTemplateBodyVisitor(templateVisitor, scriptVisitor);
        },
    };
}

module.exports = {
    dateFilter: createFilterComputedRule({
        computed: 'dateFilter',
        filter: 'date',
        components: DATE_FILTER_COMPONENTS,
    }),
    assetFilter: createFilterComputedRule({
        computed: 'assetFilter',
        filter: 'asset',
        components: ASSET_FILTER_COMPONENTS,
    }),
};
