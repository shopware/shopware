/**
 * @sw-package framework
 *
 * Resolves the `Shopware` global for the upgrade codemods: `Shopware.X`, `const { X } = Shopware` and
 * `const X = Shopware.X`.
 */

const { walk } = require('./component-tags');

const cache = new WeakMap();

/**
 * Local names bound to the `Shopware` global, mapped to their `Shopware.<path>`: `const { Module } = Shopware`
 * and `const Module = Shopware.Module`.
 */
function shopwareAliases(ast) {
    if (cache.has(ast)) {
        return cache.get(ast);
    }

    const aliases = new Map();

    walk(ast, (node) => {
        if (node.type !== 'VariableDeclarator' || !node.init) {
            return;
        }

        if (node.init.type === 'Identifier' && node.init.name === 'Shopware' && node.id.type === 'ObjectPattern') {
            for (const property of node.id.properties) {
                if (property.type === 'Property' && !property.computed && property.key.type === 'Identifier') {
                    const local = property.value.type === 'Identifier' ? property.value.name : property.key.name;

                    aliases.set(local, `Shopware.${property.key.name}`);
                }
            }
        }

        if (node.id.type === 'Identifier' && node.init.type === 'MemberExpression') {
            const resolved = memberPath(node.init, new Map());

            if (resolved && resolved.startsWith('Shopware.')) {
                aliases.set(node.id.name, resolved);
            }
        }
    });

    cache.set(ast, aliases);

    return aliases;
}

/**
 * The dotted path of a member chain: `Shopware.Module.register`, or `Module.register` with
 * `const { Module } = Shopware` → `Shopware.Module.register`.
 */
function memberPath(node, aliases) {
    if (node.type === 'Identifier') {
        return aliases.get(node.name) ?? node.name;
    }

    if (node.type !== 'MemberExpression' || node.computed || node.property.type !== 'Identifier') {
        return null;
    }

    const object = memberPath(node.object, aliases);

    return object === null ? null : `${object}.${node.property.name}`;
}

module.exports = {
    shopwareAliases,
    memberPath,
};
