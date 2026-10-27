/**
 * @sw-package framework
 *
 * Migrates the deprecated `sw-empty-state` component to `mt-empty-state`.
 *
 * Usages without an automatic equivalent (custom `icon` slot content, markup in the default slot, conditional `icon`
 * or default slot templates, dynamic bindings) are reported without a fix and stay `sw-empty-state` until they are migrated manually.
 */

const OLD_COMPONENT = 'sw-empty-state';
const NEW_COMPONENT = 'mt-empty-state';

// Props of sw-empty-state without an equivalent in mt-empty-state, keyed by their normalized name
const REMOVED_PROPS = {
    absolute: 'absolute',
    emptymodule: 'emptyModule',
    autoheight: 'autoHeight',
    color: 'color',
};

// Elements that only wrap the description text and can be unwrapped into the "description" prop
const DESCRIPTION_WRAPPERS = ['p', 'span', 'div'];

// Elements in the "icon" slot that can be converted to the "icon" prop
const ICON_ELEMENTS = ['mt-icon', 'sw-icon'];

// sw-empty-state fell back to the icon and description of the current module
const MODULE_DESCRIPTION_FALLBACK = '$t($route.meta.$module.description)';
const MODULE_ICON_FALLBACK = '$route.meta.$module.icon';

// Translation functions always return a string
const TRANSLATION_FUNCTIONS = ['$t', '$tc'];

const MUSTACHE_PATTERN = /{{([\s\S]*?)}}/;

const REASONS = {
    iconSlot: 'the "icon" slot renders custom content, but "mt-empty-state" only supports the "icon" prop',
    defaultSlot: 'the default slot renders markup, but "mt-empty-state" only supports a plain text "description"',
    dynamicBinding: 'dynamic bindings (v-bind="..." or :[...]) cannot be mapped to the new props',
    dynamicSlot: 'dynamic slot names cannot be mapped to the new slots',
    conditionalIconSlot: 'the "icon" slot template has directives like v-if, which cannot be mapped to the "icon" prop',
    conditionalDefaultSlot: 'the default slot template has directives like v-if, which cannot be mapped to the "description" prop',
    quotes: 'the description expression mixes single and double quotes',
};

function normalizeName(name) {
    return name.replace(/-/g, '').toLowerCase();
}

// vue-eslint-parser drops Twig tags from text nodes, so they are only visible in the source text
function containsTwig(text) {
    return /{%|%}|{#|#}/.test(text);
}

function getInnerSource(sourceCode, element) {
    return sourceCode.text.slice(element.startTag.range[1], element.endTag?.range[0] ?? element.startTag.range[1]);
}

function isWhitespaceText(node) {
    return node.type === 'VText' && node.value.trim() === '';
}

function encodeAttributeValue(text) {
    return text.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
}

function toJsString(text) {
    return `'${text.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
}

function toTemplateLiteralText(text) {
    return text.replace(/\\/g, '\\\\').replace(/`/g, '\\`').replace(/\$\{/g, '\\${');
}

function wrapCondition(code) {
    return /^[\w$.]+$/.test(code) ? code : `(${code})`;
}

/**
 * Whether the expression always resolves to a value, so the module fallback of sw-empty-state never applied.
 */
function isNeverNullish(expression) {
    switch (expression?.type) {
        case 'Literal':
            return expression.value !== null;
        // Binary operators always return a number, string or boolean
        case 'TemplateLiteral':
        case 'BinaryExpression':
            return true;
        case 'CallExpression':
            return expression.callee.type === 'Identifier' && TRANSLATION_FUNCTIONS.includes(expression.callee.name);
        case 'ConditionalExpression':
            return isNeverNullish(expression.consequent) && isNeverNullish(expression.alternate);
        case 'LogicalExpression':
            return expression.operator !== '&&' && isNeverNullish(expression.right);
        default:
            return false;
    }
}

/**
 * Returns the attribute text for a bound value, or null if the expression
 * can't be put into a quoted attribute.
 */
function boundAttribute(name, code) {
    if (!code.includes('"')) {
        return `:${name}="${code}"`;
    }

    if (!code.includes("'")) {
        return `:${name}='${code}'`;
    }

    return null;
}

/**
 * Builds the attribute text for a value, which is either
 * `{ kind: 'static', text }` or `{ kind: 'expr', code }`.
 */
function valueAttribute(name, value) {
    if (value.kind === 'static') {
        return `${name}="${encodeAttributeValue(value.text)}"`;
    }

    return boundAttribute(name, value.code);
}

function valueToExpression(value) {
    return value.kind === 'static' ? toJsString(value.text) : value.code;
}

function parseAttribute(attribute) {
    if (!attribute.directive) {
        return { kind: 'static', name: normalizeName(attribute.key.rawName ?? attribute.key.name) };
    }

    const directive = attribute.key.name.name;

    if (directive === 'slot') {
        return { kind: 'slot' };
    }

    if (directive !== 'bind') {
        return { kind: 'other' };
    }

    const argument = attribute.key.argument;

    if (!argument || argument.type !== 'VIdentifier') {
        return { kind: 'dynamic' };
    }

    return { kind: 'bound', name: normalizeName(argument.rawName ?? argument.name) };
}

/**
 * Reads the value of a static or bound attribute as `{ kind: 'static', text }` or `{ kind: 'expr', code }`.
 */
function readAttributeValue(sourceCode, attribute) {
    if (!attribute.directive) {
        return { kind: 'static', text: attribute.value?.value ?? '' };
    }

    if (!attribute.value?.expression) {
        return null;
    }

    return { kind: 'expr', code: sourceCode.getText(attribute.value.expression) };
}

/**
 * Whether a bound value can resolve to null or undefined, for which sw-empty-state fell back to the module value.
 */
function losesModuleFallback(sourceCode, attribute, fallback) {
    const expression = attribute.directive ? attribute.value?.expression : null;

    return !!expression
        && !isNeverNullish(expression)
        && sourceCode.getText(expression).replace(/\s/g, '') !== fallback;
}

/**
 * Renames the prop of a static or bound attribute and keeps the rest of its source text.
 */
function renameAttribute(sourceCode, attribute, newName) {
    const keyNode = attribute.directive ? attribute.key.argument : attribute.key;
    const text = sourceCode.getText(attribute);
    const start = keyNode.range[0] - attribute.range[0];
    const end = keyNode.range[1] - attribute.range[0];

    return `${text.slice(0, start)}${newName}${text.slice(end)}`;
}

function getSlotName(element) {
    if (element.type !== 'VElement' || element.name !== 'template') {
        return null;
    }

    const slotDirective = element.startTag.attributes.find((attribute) => {
        return attribute.directive && attribute.key.name.name === 'slot';
    });

    if (!slotDirective) {
        return null;
    }

    const argument = slotDirective.key.argument;

    if (!argument) {
        return { name: 'default', argument: null };
    }

    if (argument.type !== 'VIdentifier') {
        return { name: null, argument };
    }

    return { name: argument.rawName ?? argument.name, argument };
}

/**
 * Whether a slot template has attributes besides its v-slot, e.g. v-if / v-else, which render it conditionally.
 */
function hasSlotDirectives(template) {
    return template.startTag.attributes.some((attribute) => parseAttribute(attribute).kind !== 'slot');
}

/**
 * Converts the content of the default slot into a description value.
 * Returns null if the content contains markup which can't be expressed as plain text.
 */
function contentToDescription(sourceCode, nodes) {
    const meaningfulNodes = nodes.filter((node) => !isWhitespaceText(node));

    if (meaningfulNodes.length === 0) {
        return { kind: 'none' };
    }

    // Twig blocks inside the content would get lost when it is moved into the prop
    const firstNode = meaningfulNodes[0];
    const lastNode = meaningfulNodes[meaningfulNodes.length - 1];
    if (containsTwig(sourceCode.text.slice(firstNode.range[0], lastNode.range[1]))) {
        return null;
    }

    if (
        meaningfulNodes.length === 1
        && firstNode.type === 'VElement'
        && DESCRIPTION_WRAPPERS.includes(firstNode.name)
        && firstNode.startTag.attributes.length === 0
    ) {
        return contentToDescription(sourceCode, firstNode.children);
    }

    const parts = [];

    // eslint-disable-next-line no-restricted-syntax
    for (const node of nodes) {
        if (node.type === 'VText') {
            // Mustaches are only parsed in .vue files, in .twig files they are part of the text
            node.value.split(MUSTACHE_PATTERN).forEach((segment, index) => {
                parts.push(index % 2 === 0 ? { text: segment.replace(/\s+/g, ' ') } : { code: segment.trim() });
            });
        } else if (node.type === 'VExpressionContainer' && node.expression) {
            parts.push({ code: sourceCode.getText(node.expression) });
        } else {
            return null;
        }
    }

    const firstPart = parts[0];
    const lastPart = parts[parts.length - 1];
    if (firstPart.text !== undefined) {
        firstPart.text = firstPart.text.trimStart();
    }
    if (lastPart.text !== undefined) {
        lastPart.text = lastPart.text.trimEnd();
    }

    const filteredParts = parts.filter((part) => part.text !== '');

    if (filteredParts.every((part) => part.text !== undefined)) {
        return { kind: 'static', text: filteredParts.map((part) => part.text).join('') };
    }

    if (filteredParts.length === 1) {
        return { kind: 'expr', code: filteredParts[0].code };
    }

    const templateLiteral = filteredParts.map((part) => {
        return part.text !== undefined ? toTemplateLiteralText(part.text) : `\${${part.code}}`;
    }).join('');

    return { kind: 'expr', code: `\`${templateLiteral}\`` };
}

/**
 * Converts the content of the "icon" slot into an icon value.
 * Only a single mt-icon / sw-icon with a "name" can be converted.
 */
function iconSlotToIcon(sourceCode, template) {
    const meaningfulNodes = template.children.filter((node) => !isWhitespaceText(node));

    if (meaningfulNodes.length !== 1 || containsTwig(getInnerSource(sourceCode, template))) {
        return null;
    }

    const [iconElement] = meaningfulNodes;
    if (iconElement.type !== 'VElement' || !ICON_ELEMENTS.includes(iconElement.name)) {
        return null;
    }

    const nameAttribute = iconElement.startTag.attributes.find((attribute) => {
        const parsed = parseAttribute(attribute);

        return ['static', 'bound'].includes(parsed.kind) && parsed.name === 'name';
    });

    if (!nameAttribute) {
        return null;
    }

    return readAttributeValue(sourceCode, nameAttribute);
}

/**
 * Collects everything needed to migrate the element. Returns `{ reasons }` if the element
 * has to be migrated manually.
 */
function analyze(sourceCode, node) {
    const reasons = new Set();
    const result = {
        keptAttributes: [],
        removedProps: [],
        titleAttribute: null,
        sublineAttribute: null,
        iconAttribute: null,
        showDescriptionAttribute: null,
        hasCentered: false,
        iconSlot: null,
        // Several "actions" templates are possible, e.g. with v-if / v-else
        actionsSlotArguments: [],
        defaultTemplate: null,
        defaultNodes: [],
    };

    node.startTag.attributes.forEach((attribute) => {
        const parsed = parseAttribute(attribute);

        if (parsed.kind === 'dynamic') {
            reasons.add(REASONS.dynamicBinding);
            return;
        }

        if (parsed.kind === 'slot' || parsed.kind === 'other') {
            result.keptAttributes.push(attribute);
            return;
        }

        if (parsed.name === 'title') {
            result.titleAttribute = attribute;
            result.keptAttributes.push(attribute);
        } else if (parsed.name === 'subline') {
            result.sublineAttribute = attribute;
        } else if (parsed.name === 'icon') {
            result.iconAttribute = attribute;
        } else if (parsed.name === 'showdescription') {
            result.showDescriptionAttribute = attribute;
        } else if (REMOVED_PROPS[parsed.name]) {
            result.removedProps.push(REMOVED_PROPS[parsed.name]);
        } else {
            if (parsed.name === 'centered') {
                result.hasCentered = true;
            }

            result.keptAttributes.push(attribute);
        }
    });

    node.children.forEach((child) => {
        const slot = getSlotName(child);

        if (slot === null) {
            if (!isWhitespaceText(child)) {
                result.defaultNodes.push(child);
            }
            return;
        }

        if (slot.name === null) {
            reasons.add(REASONS.dynamicSlot);
        } else if (slot.name === 'default') {
            result.defaultTemplate = child;

            if (hasSlotDirectives(child)) {
                reasons.add(REASONS.conditionalDefaultSlot);
            }
        } else if (slot.name === 'icon') {
            result.iconSlot = child;

            if (hasSlotDirectives(child)) {
                reasons.add(REASONS.conditionalIconSlot);
            }
        } else if (slot.name === 'actions') {
            result.actionsSlotArguments.push(slot.argument);
        }
    });

    result.icon = result.iconSlot ? iconSlotToIcon(sourceCode, result.iconSlot) : null;
    if (result.iconSlot && !result.icon) {
        reasons.add(REASONS.iconSlot);
    }

    const hasDefaultSlot = result.defaultTemplate !== null || result.defaultNodes.length > 0;
    if (hasDefaultSlot) {
        const hasTwigInTemplate = result.defaultTemplate
            && containsTwig(getInnerSource(sourceCode, result.defaultTemplate));

        result.slotDescription = hasTwigInTemplate ? null : contentToDescription(
            sourceCode,
            result.defaultTemplate ? result.defaultTemplate.children : result.defaultNodes,
        );

        if (!result.slotDescription) {
            reasons.add(REASONS.defaultSlot);
        }
    }

    result.reasons = [...reasons];

    return result;
}

/**
 * Resolves the condition of the "showDescription" prop: true, false or an expression.
 */
function resolveShowDescription(sourceCode, attribute) {
    if (!attribute || !attribute.directive) {
        return true;
    }

    const value = readAttributeValue(sourceCode, attribute);
    if (!value || value.code === 'true') {
        return true;
    }

    if (value.code === 'false') {
        return false;
    }

    return value.code;
}

/**
 * Builds the attributes which replace the description, icon and layout of sw-empty-state.
 * Returns `{ renamedSubline, description, lostFallbacks, newAttributes }` or `{ reason }` if a value can't be expressed.
 */
function buildNewAttributes(sourceCode, analysis) {
    const newAttributes = [];
    const lostFallbacks = [];
    const showDescription = resolveShowDescription(sourceCode, analysis.showDescriptionAttribute);
    let renamedSubline = false;

    let description;
    if (analysis.slotDescription) {
        description = analysis.slotDescription;
    } else if (analysis.sublineAttribute) {
        description = readAttributeValue(sourceCode, analysis.sublineAttribute) ?? { kind: 'none' };
        renamedSubline = showDescription === true && description.kind !== 'none';

        if (
            showDescription !== false
            && losesModuleFallback(sourceCode, analysis.sublineAttribute, MODULE_DESCRIPTION_FALLBACK)
        ) {
            lostFallbacks.push('"description" no longer falls back to the module description');
        }
    } else {
        description = { kind: 'expr', code: MODULE_DESCRIPTION_FALLBACK };
    }

    if (showDescription === false) {
        // The "description" prop of mt-empty-state is typed as required, an empty one renders the same as none
        description = { kind: 'static', text: '' };
    } else if (showDescription !== true && description.kind !== 'none') {
        description = {
            kind: 'expr',
            code: `${wrapCondition(showDescription)} ? ${valueToExpression(description)} : ''`,
        };
    }

    if (!renamedSubline && description.kind !== 'none') {
        const descriptionAttribute = valueAttribute('description', description);

        if (!descriptionAttribute) {
            return { reason: REASONS.quotes };
        }

        newAttributes.push(descriptionAttribute);
    }

    if (analysis.icon) {
        const iconAttribute = valueAttribute('icon', analysis.icon);

        if (!iconAttribute) {
            return { reason: REASONS.quotes };
        }

        newAttributes.push(iconAttribute);
    } else if (!analysis.iconAttribute) {
        newAttributes.push(`:icon="${MODULE_ICON_FALLBACK}"`);
    } else if (losesModuleFallback(sourceCode, analysis.iconAttribute, MODULE_ICON_FALLBACK)) {
        lostFallbacks.push('"icon" no longer falls back to the module icon');
    }

    // sw-empty-state was centered, mt-empty-state is left-aligned by default
    if (!analysis.hasCentered) {
        newAttributes.push('centered');
    }

    return { renamedSubline, description, lostFallbacks, newAttributes };
}

/**
 * Range of a child node including the whitespace before it, so removing it doesn't leave an empty line.
 * Text nodes keep their trailing whitespace, which separates them from the next sibling.
 */
function getRemovalRange(sourceText, node) {
    let start = node.range[0];
    let end = node.range[1];

    if (node.type === 'VText') {
        const text = sourceText.slice(start, end);
        start += text.length - text.trimStart().length;
        end -= text.length - text.trimEnd().length;
    }

    while (start > 0 && /\s/.test(sourceText[start - 1])) {
        start -= 1;
    }

    return [start, end];
}

function buildTodoComment(analysis, { description, lostFallbacks }) {
    let comment = `TODO Codemod: Converted from ${OLD_COMPONENT} - please check if everything works correctly`;

    if (analysis.removedProps.length > 0) {
        comment += `. Removed props without replacement: ${analysis.removedProps.join(', ')}`;
    }

    if (description.kind === 'static' && /<[a-z]/i.test(description.text)) {
        comment += '. The description is rendered as plain text, HTML is no longer supported';
    }

    lostFallbacks.forEach((lostFallback) => {
        comment += `. ${lostFallback} if it is null or undefined`;
    });

    return `<!-- ${comment} -->`;
}

/**
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = {
    meta: {
        type: 'problem',
        fixable: 'code',
        docs: {
            description: `Migrate the deprecated "${OLD_COMPONENT}" to "${NEW_COMPONENT}"`,
            recommended: true,
        },
        messages: {
            migrate: `"${OLD_COMPONENT}" is deprecated and will be removed in 6.9. Please use "${NEW_COMPONENT}" instead.`,
            manual: `"${OLD_COMPONENT}" is deprecated and will be removed in 6.9. It can't be converted to "${NEW_COMPONENT}" automatically, because {{ reasons }}. Please migrate it manually.`,
        },
        schema: [],
    },

    /** @param {import('eslint').Rule.RuleContext} context */
    create(context) {
        const sourceCode = context.sourceCode;

        const templateVisitor = {
            VElement(node) {
                if (node.name !== OLD_COMPONENT) {
                    return;
                }

                const analysis = analyze(sourceCode, node);
                const attributes = analysis.reasons.length === 0 ? buildNewAttributes(sourceCode, analysis) : null;
                const reasons = attributes?.reason ? [attributes.reason] : analysis.reasons;

                if (reasons.length > 0) {
                    context.report({
                        node: node.startTag,
                        messageId: 'manual',
                        data: { reasons: reasons.join('; ') },
                    });

                    return;
                }

                context.report({
                    node: node.startTag,
                    messageId: 'migrate',
                    *fix(fixer) {
                        const sourceText = sourceCode.text;
                        const { startTag, endTag } = node;
                        const tagNameEnd = startTag.range[0] + 1 + OLD_COMPONENT.length;
                        const originalAttributes = startTag.attributes;
                        const lastAttributeEnd = originalAttributes.length > 0
                            ? originalAttributes[originalAttributes.length - 1].range[1]
                            : tagNameEnd;

                        // Reuse the separator of the first attribute, so single and multi line tags keep their style
                        const separator = originalAttributes.length > 0
                            ? sourceText.slice(tagNameEnd, originalAttributes[0].range[0])
                            : ' ';
                        let closing = sourceText.slice(lastAttributeEnd, startTag.range[1]);

                        // Keep the original attribute order, replaced props are added at the end
                        const attributeTexts = originalAttributes.map((attribute) => {
                            if (attribute === analysis.titleAttribute) {
                                return renameAttribute(sourceCode, attribute, 'headline');
                            }

                            if (attribute === analysis.sublineAttribute) {
                                return attributes.renamedSubline
                                    ? renameAttribute(sourceCode, attribute, 'description')
                                    : null;
                            }

                            if (attribute === analysis.iconAttribute) {
                                return analysis.icon ? null : sourceCode.getText(attribute);
                            }

                            return analysis.keptAttributes.includes(attribute) ? sourceCode.getText(attribute) : null;
                        }).filter((text) => text !== null);

                        attributeTexts.push(...attributes.newAttributes);

                        // Children which are moved into props or dropped
                        const removedChildren = [
                            analysis.iconSlot,
                            analysis.defaultTemplate,
                            ...(analysis.slotDescription ? analysis.defaultNodes : []),
                        ].filter(Boolean);

                        const remainingChildren = endTag ? node.children.filter((child) => {
                            return !removedChildren.includes(child) && !isWhitespaceText(child);
                        }) : [];

                        const selfClosing = !!endTag
                            && remainingChildren.length === 0
                            && !containsTwig(getInnerSource(sourceCode, node));
                        if (selfClosing) {
                            const trimmedClosing = closing.trimEnd();
                            closing = `${trimmedClosing.slice(0, -1)}${trimmedClosing.length === 1 ? ' />' : '/>'}`;
                        }

                        const indentation = ' '.repeat(node.loc.start.column);
                        const newStartTag = `<${NEW_COMPONENT}${attributeTexts.map((text) => `${separator}${text}`).join('')}${closing}`;

                        yield fixer.replaceText(
                            startTag,
                            `${buildTodoComment(analysis, attributes)}\n${indentation}${newStartTag}`,
                        );

                        if (!endTag) {
                            return;
                        }

                        if (selfClosing) {
                            yield fixer.removeRange([startTag.range[1], endTag.range[1]]);
                            return;
                        }

                        // Collect the child fixes in source order, the fixes of one report must not overlap
                        const childFixes = removedChildren.map((child) => {
                            return { range: getRemovalRange(sourceText, child), text: '' };
                        });

                        analysis.actionsSlotArguments.forEach((argument) => {
                            childFixes.push({ range: argument.range, text: 'button' });
                        });

                        childFixes.sort((a, b) => a.range[0] - b.range[0]);

                        // eslint-disable-next-line no-restricted-syntax
                        for (const childFix of childFixes) {
                            yield fixer.replaceTextRange(childFix.range, childFix.text);
                        }

                        yield fixer.replaceText(endTag, `</${NEW_COMPONENT}>`);
                    },
                });
            },
        };

        return sourceCode.parserServices.defineTemplateBodyVisitor(templateVisitor);
    },
};
