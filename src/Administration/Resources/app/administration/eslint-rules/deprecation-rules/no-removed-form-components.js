/**
 * @sw-package framework
 *
 * Upgrade codemod `removed-form-components`: 6.8 removes `sw-select-number-field`, `sw-entity-many-to-many-select`
 * and `sw-help-sidebar`.
 *
 * - `sw-select-number-field` becomes `mt-select`. Static `<option>` children with numeric values become `:options`,
 *   an `options` prop is kept with `value-property="id"` and `label-property="name"`, and the `value` forms become
 *   `model-value`. Option names as locale objects, `aside`, slots and other options get a TODO.
 * - `sw-entity-many-to-many-select` with `local-mode` becomes `sw-entity-multi-select` without it. Without
 *   `local-mode` the old component saved the association itself and the new one does not, so it only gets a TODO.
 * - `sw-help-sidebar` gets a TODO, `Component.override/extend` of it a TODO pointing to `sw-help-center-v2`.
 * - `Component.override/extend` of the other two components get a TODO as well.
 */

const { reportTodo } = require('./upgrade-helpers/todo-comment');
const { readComponentCall, elementName, attributeName, attributeRemovalRange } = require('./upgrade-helpers/component-tags');
const { TWIG_PATTERN, readContentExpression } = require('./utils/template-expressions');

const ID = 'removed-form-components';

const MESSAGES = {
    selectNumberField: 'sw-select-number-field is removed. Use mt-select instead.',
    manyToManySelect: 'sw-entity-many-to-many-select is removed. Use sw-entity-multi-select instead.',
    manyToManySelectManual: 'sw-entity-many-to-many-select is removed. Use sw-entity-multi-select, which does not save '
        + 'the association itself: handle @update:entity-collection and save the parent entity.',
    helpSidebar: 'sw-help-sidebar is removed without replacement. The help center content is part of sw-help-center-v2.',
};

const OVERRIDE_MESSAGES = {
    'sw-help-sidebar': 'sw-help-sidebar is removed. Move this override to sw-help-center-v2, which has the same Twig block names.',
    'sw-select-number-field': 'sw-select-number-field is removed. Its replacement mt-select is a Meteor component, '
        + 'which can not be overridden; move the customization to the templates that render it.',
    'sw-entity-many-to-many-select': 'sw-entity-many-to-many-select is removed. Move this override to sw-entity-multi-select.',
};

/**
 * @param {string} reason
 * @returns {string}
 */
function selectManualMessage(reason) {
    return 'sw-select-number-field is removed. Use mt-select with v-model and :options="[{ value: 1, label: \'…\' }]" '
        + `with numeric values. Not converted automatically because of ${reason}.`;
}

class Unconvertible extends Error {}

function fail(reason) {
    throw new Unconvertible(reason);
}

function normalize(name) {
    return name.replace(/-/g, '').toLowerCase();
}

function numericLiteral(text) {
    const trimmed = String(text).trim();

    return trimmed !== '' && Number.isFinite(Number(trimmed)) ? String(Number(trimmed)) : null;
}

/**
 * The directive name and normalized argument of a `v-on`/`v-model` attribute, or null.
 */
function directiveOf(attribute) {
    if (!attribute.directive) {
        return null;
    }

    const argument = attribute.key.argument;

    return {
        directive: attribute.key.name.name,
        argument: argument?.type === 'VIdentifier' ? argument.rawName ?? argument.name : argument ? null : '',
    };
}

function hasLocaleObjectNames(expression) {
    return expression.type === 'ArrayExpression' && expression.elements.some((element) => {
        return element?.type === 'ObjectExpression' && element.properties.some((property) => {
            return property.type === 'Property'
                && (property.key.name ?? property.key.value) === 'name'
                && property.value.type === 'ObjectExpression';
        });
    });
}

/**
 * Reads static `<option value="1">Label</option>` children as `{ value: 1, label: 'Label' }` sources.
 */
function readOptions(sourceCode, element) {
    const inner = element.endTag ? sourceCode.text.slice(element.startTag.range[1], element.endTag.range[0]) : '';

    if (TWIG_PATTERN.test(inner) || /<!--/.test(inner)) {
        fail('Twig or comments in the options');
    }

    return element.children.flatMap((child) => {
        if (child.type === 'VText' && child.value.trim() === '') {
            return [];
        }

        if (child.type !== 'VElement') {
            fail('text next to the options');
        }

        if (elementName(child) !== 'option') {
            const slot = child.startTag.attributes.find((attribute) => directiveOf(attribute)?.directive === 'slot');

            return fail(slot ? `the #${directiveOf(slot).argument || 'default'} slot` : `a <${child.rawName ?? child.name}> element`);
        }

        let value = null;

        child.startTag.attributes.forEach((attribute) => {
            const name = attributeName(attribute);

            if (name !== 'value') {
                fail(`the ${sourceCode.getText(attribute.key)} attribute on option`);
            }

            const raw = attribute.directive ? attribute.value?.expression : attribute.value?.value;
            const literal = attribute.directive ? raw?.type === 'Literal' && raw.value : raw;

            value = numericLiteral(literal ?? '') ?? fail('an option value that is not a number');
        });

        const label = readContentExpression(sourceCode, child) ?? fail('an option label with markup');

        if (value === null || label.code === null) {
            fail('an option without value or label');
        }

        return [`{ value: ${value}, label: ${label.code} }`];
    });
}

/**
 * Builds the `mt-select` start tag for a `sw-select-number-field`, or throws `Unconvertible`.
 */
function convertSelectNumberField(sourceCode, element) {
    const edits = [];
    let options = null;
    let hasValueProperty = false;
    let hasLabelProperty = false;

    element.startTag.attributes.forEach((attribute) => {
        const directive = directiveOf(attribute);

        if (directive && directive.directive !== 'bind') {
            if (directive.argument === null) {
                fail('a dynamic argument');
            }

            if (directive.directive === 'model' && directive.argument === 'value') {
                edits.push({ range: attribute.key.range, text: 'v-model' });
            } else if (directive.directive === 'on' && directive.argument === 'update:value') {
                edits.push({ range: attribute.key.argument.range, text: 'update:model-value' });
            } else if (directive.directive === 'slot') {
                fail('v-slot on sw-select-number-field');
            }

            return;
        }

        const name = attributeName(attribute);

        if (name === null) {
            fail('v-bind without an argument');
        }

        switch (normalize(name)) {
            case 'value':
                if (attribute.directive) {
                    edits.push({ range: attribute.key.argument.range, text: 'model-value' });
                } else {
                    const value = numericLiteral(attribute.value?.value ?? '') ?? fail('a value that is not a number');

                    edits.push({ range: attribute.range, text: `:model-value="${value}"` });
                }

                return;
            case 'aside':
                return fail('aside, which mt-select has no replacement for');
            case 'options':
                if (!attribute.directive || !attribute.value?.expression) {
                    fail('static options');
                }

                if (hasLocaleObjectNames(attribute.value.expression)) {
                    fail('option names as locale objects');
                }

                options = 'prop';
                return;
            case 'valueproperty':
                hasValueProperty = true;
                return;
            case 'labelproperty':
                hasLabelProperty = true;
                break;
            default:
        }
    });

    const children = readOptions(sourceCode, element);
    const added = [];

    if (children.length > 0) {
        if (options) {
            fail('options and option children together');
        }

        added.push(`:options="[${children.join(', ')}]"`);
    } else if (options) {
        if (!hasValueProperty) {
            added.push('value-property="id"');
        }

        if (!hasLabelProperty) {
            added.push('label-property="name"');
        }
    } else {
        fail('missing options');
    }

    return buildStartTag(sourceCode, element, 'mt-select', edits, added);
}

/**
 * Rebuilds the start tag with a new name, replaced attribute parts and added attributes, as a self-closing tag.
 * Keeps the original line breaks between the attributes.
 */
function buildStartTag(sourceCode, element, newName, edits, added) {
    const startTag = element.startTag;
    const attributes = startTag.attributes;
    const last = attributes[attributes.length - 1];
    const nameEnd = startTag.range[0] + 1 + (element.rawName ?? element.name).length;
    const attributesEnd = last ? last.range[1] : nameEnd;
    const separator = last ? /\s*$/.exec(sourceCode.text.slice(nameEnd, last.range[0]))[0] || ' ' : ' ';

    const attributeText = [...edits]
        .sort((a, b) => b.range[0] - a.range[0])
        .reduce((text, edit) => {
            return `${text.slice(0, edit.range[0] - nameEnd)}${edit.text}${text.slice(edit.range[1] - nameEnd)}`;
        }, sourceCode.text.slice(nameEnd, attributesEnd));

    const tail = sourceCode.text.slice(attributesEnd, startTag.range[1]).replace(/\/?>$/, '');
    const closing = `${tail || ' '}/>`;

    return `<${newName}${attributeText}${added.map((attribute) => `${separator}${attribute}`).join('')}${closing}`;
}

/**
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = {
    meta: {
        type: 'problem',
        fixable: 'code',
        docs: {
            description: 'Migrate or mark the form components sw-select-number-field, sw-entity-many-to-many-select and sw-help-sidebar, which 6.8 removes',
        },
        schema: [],
    },

    /** @param {import('eslint').Rule.RuleContext} context */
    create(context) {
        const sourceCode = context.sourceCode;
        const seen = new Set();
        const todo = (node, message, scope) => reportTodo(context, { id: ID, message, range: node.range, scope, seen });

        const scriptVisitors = {
            CallExpression(node) {
                const call = readComponentCall(node);

                if (call && ['override', 'extend'].includes(call.method) && OVERRIDE_MESSAGES[call.target]) {
                    todo(node, OVERRIDE_MESSAGES[call.target], 'script');
                }
            },
        };

        if (!sourceCode.parserServices?.defineTemplateBodyVisitor) {
            return scriptVisitors;
        }

        const handlers = {
            'sw-help-sidebar'(node) {
                todo(node, MESSAGES.helpSidebar, 'template');
            },

            'sw-select-number-field'(node) {
                let startTag;

                try {
                    startTag = convertSelectNumberField(sourceCode, node);
                } catch (error) {
                    if (!(error instanceof Unconvertible)) {
                        throw error;
                    }

                    todo(node, selectManualMessage(error.message), 'template');

                    return;
                }

                context.report({
                    node: node.startTag,
                    message: MESSAGES.selectNumberField,
                    fix: (fixer) => fixer.replaceTextRange(node.range, startTag),
                });
            },

            'sw-entity-many-to-many-select'(node) {
                const localMode = node.startTag.attributes.find((attribute) => normalize(attributeName(attribute) ?? '') === 'localmode');
                const expression = localMode?.directive ? localMode.value?.expression : null;
                const isLocal = localMode && (!localMode.directive || (expression?.type === 'Literal' && expression.value === true));

                if (!isLocal) {
                    todo(node, MESSAGES.manyToManySelectManual, 'template');

                    return;
                }

                const nameStart = node.startTag.range[0] + 1;

                context.report({
                    node: node.startTag,
                    message: MESSAGES.manyToManySelect,
                    fix: (fixer) => [
                        fixer.replaceTextRange([nameStart, nameStart + (node.rawName ?? node.name).length], 'sw-entity-multi-select'),
                        fixer.removeRange(attributeRemovalRange(sourceCode, localMode)),
                        ...(node.endTag ? [fixer.replaceTextRange(node.endTag.range, '</sw-entity-multi-select>')] : []),
                    ],
                });
            },
        };

        return sourceCode.parserServices.defineTemplateBodyVisitor({
            VElement(node) {
                handlers[elementName(node)]?.(node);
            },
        }, scriptVisitors);
    },
};
