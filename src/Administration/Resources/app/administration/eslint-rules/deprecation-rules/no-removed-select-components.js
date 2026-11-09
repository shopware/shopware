/**
 * @sw-package framework
 *
 * Shopware 6.8 removes two select components:
 * - `sw-select-number-field` becomes `mt-select`. `<option>` children with a number value become
 *   the `options` prop, an `options` prop of `{ id, name }` objects gets `value-property` and
 *   `label-property`. The `no-deprecated-component-usage` codemod then moves `v-model:value` to `v-model`.
 * - `sw-entity-many-to-many-select` becomes `sw-entity-multi-select`, which never saves on its own.
 *   That is the behaviour of `local-mode`, so the attribute goes away. Without a static `local-mode`
 *   the codemod also writes a TODO comment, because the parent now has to save the collection.
 */

const { createTodoReporter, todoMessages } = require('./utils/todo-comment');
const {
    attributeRemovalRange,
    propAttributes,
    renameElement,
    startTagAttributesText,
} = require('./utils/template-elements');

const OPTIONS_HINT = 'sw-select-number-field is removed in 6.8. Pass the options to mt-select with the options prop, ' +
    'as objects with a number value and a label.';
const LOCAL_MODE_HINT = 'sw-entity-many-to-many-select is removed in 6.8. sw-entity-multi-select does not save the ' +
    'assignment on its own, save the entity collection when the parent entity is saved.';

/**
 * `{{ $t('key') }}` in a `.html.twig` template, where the Vue parser keeps interpolations as text.
 */
const TWIG_TRANSLATION = /^\{\{\s*\$tc?\(\s*(['"])([^'"]+)\1\s*\)\s*\}\}$/;

/**
 * The `{ value, label }` source of an `<option>`, or null when it has no static number value or label.
 *
 * @param {import('eslint').SourceCode} sourceCode
 * @param {import('vue-eslint-parser').AST.VElement} option
 */
function optionItem(sourceCode, option) {
    const [valueAttribute] = propAttributes(option, ['value']);
    let value = null;

    if (valueAttribute && !valueAttribute.directive && /^-?\d+(\.\d+)?$/.test(valueAttribute.value?.value ?? '')) {
        value = valueAttribute.value.value;
    } else if (valueAttribute?.directive && typeof valueAttribute.value?.expression?.value === 'number') {
        value = sourceCode.getText(valueAttribute.value.expression);
    }

    const content = option.children.filter((child) => !(child.type === 'VText' && child.value.trim() === ''));

    if (value === null || content.length !== 1) {
        return null;
    }

    const [child] = content;

    if (child.type === 'VExpressionContainer' && child.expression?.type === 'CallExpression') {
        const callee = sourceCode.getText(child.expression.callee);

        return ['$t', '$tc'].includes(callee) ? `{ value: ${value}, label: ${sourceCode.getText(child.expression).replace(/^\$tc/, '$t')} }` : null;
    }

    if (child.type !== 'VText') {
        return null;
    }

    const text = child.value.trim();
    const translation = TWIG_TRANSLATION.exec(text);

    if (translation) {
        return `{ value: ${value}, label: $t('${translation[2]}') }`;
    }

    if (/[{}"<>]/.test(text)) {
        return null;
    }

    return `{ value: ${value}, label: '${text.replace(/\\/g, '\\\\').replace(/'/g, '\\\'')}' }`;
}

/** @type {import('eslint').Rule.RuleModule} */
module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Replace the select components that Shopware 6.8 removes',
            category: 'Shopware 6.8 Migration',
            recommended: false,
        },
        fixable: 'code',
        schema: [],
        messages: {
            ...todoMessages,
            replace: '"{{ name }}" is removed in 6.8. Use "{{ replacement }}" instead.',
        },
    },
    create(context) {
        const sourceCode = context.sourceCode;
        const text = sourceCode.text;
        const reporter = createTodoReporter(context);
        const parserServices = sourceCode.parserServices;

        if (!parserServices?.defineTemplateBodyVisitor) {
            return {};
        }

        const replace = (node, replacement, fix) => {
            context.report({
                loc: node.loc,
                messageId: 'replace',
                data: { name: node.rawName, replacement },
                fix,
            });
        };

        const checkNumberField = (node) => {
            const options = node.children.filter((child) => !(child.type === 'VText' && child.value.trim() === ''));
            const hasOptionsProp = propAttributes(node, ['options']).length > 0;

            if (hasOptionsProp || options.length === 0) {
                const hasProperties = propAttributes(node, ['value-property', 'label-property']).length > 0;
                const nameEnd = node.startTag.range[0] + 1 + node.rawName.length;

                replace(node, 'mt-select', (fixer) => [
                    ...renameElement(fixer, node, 'mt-select'),
                    ...(hasOptionsProp && !hasProperties
                        ? [fixer.insertTextAfterRange([nameEnd, nameEnd], ' value-property="id" label-property="name"')]
                        : []),
                ]);

                return;
            }

            const items = options.map((option) => (option.type === 'VElement' && option.name === 'option' ? optionItem(sourceCode, option) : null));

            if (items.some((item) => item === null)) {
                reporter.reportTemplate(node, OPTIONS_HINT);
                replace(node, 'mt-select', (fixer) => renameElement(fixer, node, 'mt-select'));

                return;
            }

            replace(node, 'mt-select', (fixer) => fixer.replaceTextRange(
                node.range,
                `<mt-select${startTagAttributesText(text, node)} :options="[${items.join(', ')}]" />`,
            ));
        };

        const checkManyToManySelect = (node) => {
            const localMode = propAttributes(node, ['local-mode']);
            const isStaticTrue = localMode.length === 1 && (localMode[0].directive
                ? localMode[0].value?.expression?.value === true
                : [undefined, '', 'true'].includes(localMode[0].value?.value));

            if (!isStaticTrue) {
                reporter.reportTemplate(node, LOCAL_MODE_HINT);
            }

            replace(node, 'sw-entity-multi-select', (fixer) => [
                ...renameElement(fixer, node, 'sw-entity-multi-select'),
                ...localMode.map((attribute) => fixer.removeRange(attributeRemovalRange(text, attribute))),
            ]);
        };

        return parserServices.defineTemplateBodyVisitor({
            VElement(node) {
                switch (node.rawName) {
                    case 'sw-select-number-field':
                        checkNumberField(node);
                        break;
                    case 'sw-entity-many-to-many-select':
                        checkManyToManySelect(node);
                        break;
                    // The rewritten tags keep their TODO comments in the report until someone removes them.
                    case 'mt-select':
                        reporter.reportTemplateIfWritten(node, OPTIONS_HINT);
                        break;
                    case 'sw-entity-multi-select':
                        reporter.reportTemplateIfWritten(node, LOCAL_MODE_HINT);
                        break;
                    default:
                        break;
                }
            },
        });
    },
};
