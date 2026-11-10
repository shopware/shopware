/**
 * @sw-package framework
 *
 * Helpers for registry rules that read template expressions: attribute names, template-local
 * variables, and the `{{ … }}` mustaches of `.html.twig` templates, which vue-eslint-parser keeps as
 * raw text.
 */

const espree = require('espree');
const { toComparableName } = require('./template-ast');

const MUSTACHE = /\{\{([\s\S]*?)\}\}/g;

const FUNCTION_TYPES = new Set([
    'ArrowFunctionExpression',
    'FunctionExpression',
]);

// Every character but line breaks, so a blanked prefix keeps the lines and columns of the file.
const NOT_A_LINE_BREAK = /[^\r\n\u2028\u2029]/g;

const blankedTexts = new WeakMap();
const mustacheOfExpression = new WeakMap();

/**
 * The source text with every character but line breaks replaced by a space, so an expression parsed
 * behind a slice of it gets the ranges and locations of the file.
 */
function blankedText(sourceCode) {
    if (!blankedTexts.has(sourceCode)) {
        blankedTexts.set(sourceCode, sourceCode.text.replace(NOT_A_LINE_BREAK, ' '));
    }

    return blankedTexts.get(sourceCode);
}

/**
 * Sets the `parent` of every node below `node`; the root gets `parent`.
 */
function setParents(node, parent) {
    node.parent = parent;

    (espree.VisitorKeys[node.type] ?? []).forEach((key) => {
        [node[key]]
            .flat()
            .filter((child) => child && typeof child.type === 'string')
            .forEach((child) => setParents(child, node));
    });
}

/**
 * Calls `visit` for `node` and every node below it.
 *
 * @example
 * walkExpression(expression, (node) => node.type === 'Identifier' && names.push(node.name));
 */
function walkExpression(node, visit) {
    visit(node);

    (espree.VisitorKeys[node.type] ?? []).forEach((key) => {
        [node[key]]
            .flat()
            .filter((child) => child && typeof child.type === 'string')
            .forEach((child) => walkExpression(child, visit));
    });
}

/**
 * Parses the JavaScript expression of every `{{ … }}` in a text node of an `.html.twig` template.
 * The nodes carry the ranges and locations of the file, so reports and fixes work on them as on
 * parsed template expressions, and the root's parent is the text node, so they reach the elements
 * around them. Mustaches `isCandidate` rejects and expressions that do not parse are skipped.
 *
 * @example
 * parseMustaches(sourceCode, textNode, (code) => code.includes('dateFilter')); // [CallExpression dateFilter(item.createdAt)]
 */
function parseMustaches(sourceCode, textNode, isCandidate = () => true) {
    const raw = sourceCode.text.slice(textNode.range[0], textNode.range[1]);
    const expressions = [];

    for (const mustache of raw.matchAll(MUSTACHE)) {
        const code = mustache[1];

        if (!isCandidate(code)) {
            continue;
        }

        const start = textNode.range[0] + mustache.index;
        const expressionStart = start + 2;
        // The second `{` becomes a parenthesis, so an object literal parses as an expression.
        const padded = `${blankedText(sourceCode).slice(0, expressionStart - 1)}(${code})`;
        let program;

        try {
            program = espree.parse(padded, { ecmaVersion: 'latest', sourceType: 'module', range: true, loc: true });
        } catch {
            continue;
        }

        const statement = program.body[0];

        if (program.body.length !== 1 || statement.type !== 'ExpressionStatement') {
            continue;
        }

        const expression = statement.expression;

        setParents(expression, textNode);
        mustacheOfExpression.set(expression, {
            range: [start, expressionStart + code.length + 2],
            loc: {
                start: sourceCode.getLocFromIndex(start),
                end: sourceCode.getLocFromIndex(expressionStart + code.length + 2),
            },
        });
        expressions.push(expression);
    }

    return expressions;
}

/**
 * The expression container a template node sits in: the `VExpressionContainer` of an attribute or a
 * `.vue` mustache, or the `{{ … }}` of a `.html.twig` mustache that `parseMustaches` parsed; null
 * outside of one.
 *
 * @example
 * expressionContainerOf(identifierInMustache).loc.start; // the position of `{{`
 */
function expressionContainerOf(node) {
    for (let current = node; current; current = current.parent) {
        if (current.type === 'VExpressionContainer') {
            return current;
        }

        if (mustacheOfExpression.has(current)) {
            return mustacheOfExpression.get(current);
        }
    }

    return null;
}

/**
 * Whether a template identifier reads a variable rather than names a member or property:
 * `isLoading` of `isLoading && x` or `{ isLoading }`, not of `order.isLoading` or `{ isLoading: x }`.
 *
 * @example
 * isTemplateReference(identifierOf('item.isLoading').property); // false
 */
function isTemplateReference(identifier) {
    const parent = identifier.parent;

    if (parent?.type === 'MemberExpression' && parent.property === identifier && !parent.computed) {
        return false;
    }

    return !(parent?.type === 'Property' && parent.key === identifier && !parent.computed && !parent.shorthand);
}

/**
 * The names a function parameter declares.
 */
function parameterNames(pattern) {
    switch (pattern?.type) {
        case 'Identifier':
            return [pattern.name];
        case 'ObjectPattern':
            return pattern.properties.flatMap((property) => {
                return parameterNames(property.type === 'RestElement' ? property.argument : property.value);
            });
        case 'ArrayPattern':
            return pattern.elements.flatMap(parameterNames);
        case 'RestElement':
            return parameterNames(pattern.argument);
        case 'AssignmentPattern':
            return parameterNames(pattern.left);
        default:
            return [];
    }
}

/**
 * Whether a template identifier is a variable of the template itself: a `v-for` alias, a slot prop
 * or a parameter of an arrow function in the expression. Such a name is no member of the component.
 *
 * @example
 * isTemplateLocal(identifierIn('<li v-for="isLoading in list">{{ isLoading }}</li>')); // true
 */
function isTemplateLocal(identifier) {
    const name = identifier.name;

    for (let node = identifier.parent; node; node = node.parent) {
        if (node.type === 'VElement' && node.variables?.some((variable) => variable.id.name === name)) {
            return true;
        }

        if (FUNCTION_TYPES.has(node.type) && node.params.some((param) => parameterNames(param).includes(name))) {
            return true;
        }
    }

    return false;
}

/**
 * The name an attribute sets, comparable to a registry name: `is-editing` for `is-editing="x"`,
 * `:is-editing` and `v-model:is-editing`, `@loading-change` for `@loading-change` and
 * `v-on:loading-change`, `model-value` for a plain `v-model`. Null for dynamic arguments and other
 * directives.
 *
 * @example
 * templateAttributeName(attributeOf('@loadingChange="x"')); // { listener: true, name: 'loadingchange' }
 */
function templateAttributeName(attribute) {
    if (!attribute.directive) {
        return { listener: false, name: toComparableName(attribute.key.rawName ?? attribute.key.name) };
    }

    const directive = attribute.key.name.name;
    const argument = attribute.key.argument;

    if (directive === 'model' && !argument) {
        return { listener: false, name: toComparableName('model-value') };
    }

    if (
        ![
            'bind',
            'on',
            'model',
        ].includes(directive) ||
        argument?.type !== 'VIdentifier'
    ) {
        return null;
    }

    return { listener: directive === 'on', name: toComparableName(argument.rawName ?? argument.name) };
}

/**
 * Whether an attribute is one of the registry names: `@name` for a listener, `name` for a prop,
 * attribute or model, in kebab-case or camelCase.
 *
 * @example
 * matchesTemplateAttribute(attributeOf(':isEditing="x"'), ['is-editing']); // true
 */
function matchesTemplateAttribute(attribute, names) {
    const actual = templateAttributeName(attribute);

    if (!actual) {
        return false;
    }

    return names.some((name) => {
        const listener = name.startsWith('@');

        return listener === actual.listener && toComparableName(listener ? name.slice(1) : name) === actual.name;
    });
}

/**
 * Whether an element is one of the named components or tags, in kebab-case or PascalCase.
 *
 * @example
 * matchesElementName(elementOf('<SwPopover>'), ['sw-popover']); // true
 */
function matchesElementName(element, names) {
    const actual = toComparableName(element.rawName ?? element.name);

    return names.some((name) => toComparableName(name) === actual);
}

module.exports = {
    expressionContainerOf,
    isTemplateLocal,
    isTemplateReference,
    matchesElementName,
    matchesTemplateAttribute,
    parseMustaches,
    walkExpression,
};
