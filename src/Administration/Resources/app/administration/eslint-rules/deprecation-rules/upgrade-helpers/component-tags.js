/**
 * @sw-package framework
 *
 * Finds component references for the upgrade codemods: `Component.override/extend/register/build` calls in
 * scripts, and element names and attributes in templates.
 */

const COMPONENT_METHODS = ['override', 'extend', 'register', 'build', 'wrapComponentConfig'];

/**
 * Whether `node` is `Component` or `Shopware.Component`.
 */
function isComponentFactory(node) {
    if (node.type === 'Identifier') {
        return node.name === 'Component';
    }

    return (
        node.type === 'MemberExpression' &&
        !node.computed &&
        node.object.type === 'Identifier' &&
        node.object.name === 'Shopware' &&
        node.property.type === 'Identifier' &&
        node.property.name === 'Component'
    );
}

function stringValue(node) {
    if (!node) {
        return null;
    }

    if (node.type === 'Literal' && typeof node.value === 'string') {
        return node.value;
    }

    if (node.type === 'TemplateLiteral' && node.expressions.length === 0) {
        return node.quasis[0].value.cooked;
    }

    return null;
}

/**
 * Reads a `Component.<method>(…)` call.
 *
 * - `override('sw-x', options)`: `name` and `target` are `sw-x`.
 * - `extend('acme-x', 'sw-x', options)`: `name` is `acme-x`, `target` is `sw-x`.
 * - `register('acme-x', options)` and `build('sw-x')`: `name` and `target` are the first argument.
 */
function readComponentCall(node) {
    if (node.type !== 'CallExpression' || node.callee.type !== 'MemberExpression' || node.callee.computed) {
        return null;
    }

    const method = node.callee.property.type === 'Identifier' ? node.callee.property.name : null;

    if (!COMPONENT_METHODS.includes(method) || !isComponentFactory(node.callee.object)) {
        return null;
    }

    const [first, second, third] = node.arguments;
    const name = stringValue(first);

    if (method === 'extend') {
        return { method, name, target: stringValue(second), options: third ?? null, call: node };
    }

    if (method === 'wrapComponentConfig') {
        return { method, name: null, target: null, options: first ?? null, call: node };
    }

    return { method, name, target: name, options: second ?? null, call: node };
}

/**
 * The name of a template element as written, lowercased (`<sw-tabs>`, `<SwTabs>` → `sw-tabs`).
 */
function elementName(element) {
    return String(element.rawName ?? element.name)
        .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
        .toLowerCase();
}

/**
 * The key of an attribute or directive without `:`/`v-bind:` (`:resize-width` → `resize-width`).
 */
function attributeName(attribute) {
    if (!attribute.directive) {
        return attribute.key.rawName ?? attribute.key.name;
    }

    if (attribute.key.name?.name !== 'bind' || !attribute.key.argument || attribute.key.argument.type !== 'VIdentifier') {
        return null;
    }

    return attribute.key.argument.rawName ?? attribute.key.argument.name;
}

/**
 * A prop or attribute name without dashes and case, so `resize-width`, `resizeWidth` and `resizewidth` compare equal.
 */
function normalizedName(name) {
    return name.replace(/-/g, '').toLowerCase();
}

/**
 * The normalized key of a static attribute or `v-bind`, or null for other directives.
 */
function attributeKey(attribute) {
    const name = attributeName(attribute);

    return name === null ? null : normalizedName(name);
}

/**
 * Finds a static attribute or `v-bind` of an element by its name in any case style.
 */
function findAttribute(element, name) {
    const key = normalizedName(name);

    return element.startTag.attributes.find((attribute) => attributeKey(attribute) === key);
}

/**
 * The range of an attribute including the whitespace before it, for removing it cleanly.
 */
function attributeRemovalRange(sourceCode, attribute) {
    const text = sourceCode.text;
    let start = attribute.range[0];

    while (start > 0 && /\s/.test(text[start - 1])) {
        start -= 1;
    }

    return [start, attribute.range[1]];
}

/**
 * Visits every node below `root` depth-first. Skips `parent` links and token/comment arrays.
 */
function walk(root, visit) {
    const stack = [root];

    while (stack.length > 0) {
        const node = stack.pop();

        if (!node || typeof node.type !== 'string') {
            continue;
        }

        visit(node);

        for (const key of Object.keys(node)) {
            if (key === 'parent' || key === 'tokens' || key === 'comments' || key === 'templateBody') {
                continue;
            }

            const value = node[key];

            if (Array.isArray(value)) {
                for (let index = value.length - 1; index >= 0; index -= 1) {
                    stack.push(value[index]);
                }
            } else if (value && typeof value.type === 'string') {
                stack.push(value);
            }
        }
    }
}

module.exports = {
    walk,
    stringValue,
    readComponentCall,
    elementName,
    attributeName,
    normalizedName,
    attributeKey,
    findAttribute,
    attributeRemovalRange,
};
