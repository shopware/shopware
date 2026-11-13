/**
 * @sw-package framework
 *
 * Resolves global and service access for the upgrade codemods:
 * `Shopware.X`, `const { X } = Shopware`, `Shopware.Service('<name>')`, `this.<service>` and injected services.
 */

const { walk, stringValue } = require('./component-tags');

const cache = new WeakMap();

/**
 * Local names bound to the `Shopware` global, mapped to their `Shopware.<path>`: `const { State, Service } = Shopware`
 * and `const State = Shopware.State`.
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
 * The dotted path of a member chain.
 *
 * - `Shopware.State.get` → `Shopware.State.get`
 * - `State.get` with `const { State } = Shopware` → `Shopware.State.get`
 * - `this.mailService.send` → `this.mailService.send`
 * - `Shopware.Service('mailService').send` → `Service(mailService).send`
 */
function memberPath(node, aliases) {
    if (node.type === 'Identifier') {
        return aliases.get(node.name) ?? node.name;
    }

    if (node.type === 'ThisExpression') {
        return 'this';
    }

    if (node.type === 'CallExpression') {
        const callee = memberPath(node.callee, aliases);
        const argument = stringValue(node.arguments[0]);

        if ((callee === 'Shopware.Service' || callee === 'Service') && argument) {
            return `Service(${argument})`;
        }

        return null;
    }

    if (node.type === 'ChainExpression') {
        return memberPath(node.expression, aliases);
    }

    if (node.type !== 'MemberExpression') {
        return null;
    }

    const object = memberPath(node.object, aliases);

    if (object === null) {
        return null;
    }

    if (!node.computed && node.property.type === 'Identifier') {
        return `${object}.${node.property.name}`;
    }

    const key = stringValue(node.property);

    return key === null ? null : `${object}.${key}`;
}

module.exports = {
    shopwareAliases,
    memberPath,
};
