/**
 * @sw-package framework
 *
 * Shopware 6.8 removes three Administration service methods:
 * - `numberRangeService.previewPattern(typeName, …)`: the codemod rewrites the call core used,
 *   `previewPattern(numberRange.type.technicalName, …)`, to `previewPatternByNumberRangeId(numberRange.id, …)`.
 *   Other calls get a review comment, because the first argument changes from a type name to a number range id.
 * - `integrationService.updateAdmin()`: a review comment, because the repository saves the whole entity.
 * - `validationService.email()`: a review comment, because the replacement validates on the server and is async.
 *   This includes the `email` rule of the `validation` attribute and the `email` import of the service file.
 */

const { withRunLevel } = require('./registry/run-level');
const { isService, propertyName } = require('./registry/component-scope');

const HINTS = {
    previewPattern: 'numberRangeService.previewPattern() was removed. ' +
        'Use previewPatternByNumberRangeId(numberRange.id, pattern, start), it needs a saved number range.',
    updateAdmin: 'integrationService.updateAdmin() was removed. ' +
        'Set integration.admin and save the integration with repositoryFactory.create(\'integration\').save(integration).',
    email: 'validationService.email() was removed. ' +
        'Use await validationApiService.validateEmailAddress(value), it validates on the server.',
};

const METHODS = {
    numberRangeService: 'previewPattern',
    integrationService: 'updateAdmin',
    validationService: 'email',
};

/**
 * `numberRange.type.technicalName` → `numberRange`, otherwise null.
 *
 * @param {import('eslint').Rule.Node} node
 */
function numberRangeOfTypeName(node) {
    if (node?.type !== 'MemberExpression' || propertyName(node) !== 'technicalName') {
        return null;
    }

    const type = node.object;

    return type.type === 'MemberExpression' && propertyName(type) === 'type' ? type.object : null;
}

/** @type {import('eslint').Rule.RuleModule} */
module.exports = withRunLevel({
    meta: {
        type: 'problem',
        docs: {
            description: 'Replace the Administration service methods that Shopware 6.8 removes',
            category: 'Shopware 6.8 Migration',
            recommended: false,
        },
        fixable: 'code',
        schema: [],
        messages: {
            previewPattern: 'numberRangeService.previewPattern() was removed. Use previewPatternByNumberRangeId() with the number range id instead.',
        },
    },
    create(context) {
        /** A spot without a safe rewrite; at the partial run level it gets a review comment. */
        const reportManual = (node, hint) => context.report({ node, message: hint, fixLevel: 'manual' });
        const sourceCode = context.sourceCode;

        const scriptVisitor = {
            MemberExpression(node) {
                const method = propertyName(node);
                const service = Object.keys(METHODS).find((name) => METHODS[name] === method && isService(node.object, name));

                if (!service) {
                    return;
                }

                const call = node.parent.type === 'CallExpression' && node.parent.callee === node ? node.parent : null;
                const numberRange = method === 'previewPattern' && !node.computed ? numberRangeOfTypeName(call?.arguments[0]) : null;

                if (!numberRange) {
                    reportManual(node, HINTS[method]);

                    return;
                }

                context.report({
                    node: call,
                    messageId: 'previewPattern',
                    *fix(fixer) {
                        yield fixer.replaceText(node.property, 'previewPatternByNumberRangeId');
                        yield fixer.replaceText(call.arguments[0], `${sourceCode.getText(numberRange)}.id`);
                    },
                });
            },

            ImportDeclaration(node) {
                const isValidationService = /(^|\/)core\/service\/validation\.service(\.[jt]s)?$/.test(node.source.value);
                const importsEmail = node.specifiers.some((specifier) => {
                    return specifier.type === 'ImportSpecifier' && (specifier.imported.name ?? specifier.imported.value) === 'email';
                });

                if (isValidationService && importsEmail) {
                    reportManual(node, HINTS.email);
                }
            },
        };

        const parserServices = sourceCode.parserServices;

        if (!parserServices?.defineTemplateBodyVisitor) {
            return scriptVisitor;
        }

        return parserServices.defineTemplateBodyVisitor({
            // The `validation` mixin and `useValidation()` resolve `validation="required,email"` via validationService.
            VAttribute(node) {
                if (node.directive || node.key.name !== 'validation' || !node.value) {
                    return;
                }

                const rules = node.value.value.split(',').map((rule) => rule.trim());

                if (rules.includes('email')) {
                    reportManual(node.parent.parent, HINTS.email);
                }
            },
        }, scriptVisitor);
    },
});
