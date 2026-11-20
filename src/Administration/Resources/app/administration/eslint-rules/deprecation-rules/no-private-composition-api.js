/**
 * @sw-package framework
 *
 * Upgrade codemod `composition-api-private`: 6.8 makes the Composition API extension system on `Shopware.Component`
 * private. The rule marks calls of its functions, imports of `src/app/adapter/composition-extension-system` and
 * destructuring of the functions from `Shopware.Component`. A call of `overrideComponentSetup()(name, …)` gets a comment
 * that names the `.override.vue` file to move the override to. The texts are in `no-private-composition-api.data.js`.
 */

const { createTodoRule } = require('./upgrade-helpers/create-todo-rule');
const { walk, stringValue } = require('./upgrade-helpers/component-tags');
const data = require('./no-private-composition-api.data');

const PRIVATE_NAMES = [...Object.keys(data.functions), data.overrideFunction];
const COMPONENT_PATHS = ['Shopware.Component', 'Component'];

/** The private functions a `const { … } = Shopware.Component` declaration reads, as `{ name, local, node }`. */
function destructuredFunctions(declarator, api) {
    if (declarator.id.type !== 'ObjectPattern' || !declarator.init || !COMPONENT_PATHS.includes(api.memberPath(declarator.init))) {
        return [];
    }

    return declarator.id.properties
        .filter((property) => property.type === 'Property' && !property.computed && property.key.type === 'Identifier')
        .filter((property) => PRIVATE_NAMES.includes(property.key.name))
        .map((property) => ({
            name: property.key.name,
            local: property.value.type === 'Identifier' ? property.value.name : property.key.name,
            node: property,
        }));
}

module.exports = createTodoRule({
    ...data,
    visitors: (context, api) => {
        let locals = new Map();

        const functionName = (node) => {
            if (node.type === 'Identifier') {
                return locals.get(node.name) ?? null;
            }

            const path = node.type === 'MemberExpression' ? api.memberPath(node) : null;

            return PRIVATE_NAMES.find((name) => COMPONENT_PATHS.some((prefix) => path === `${prefix}.${name}`)) ?? null;
        };

        return {
            Program(program) {
                locals = new Map();

                walk(program, (node) => {
                    if (node.type === 'VariableDeclarator') {
                        destructuredFunctions(node, api).forEach(({ name, local }) => locals.set(local, name));
                    }
                });
            },
            VariableDeclarator(node) {
                for (const { name, node: property } of destructuredFunctions(node, api)) {
                    api.todo(property, name === data.overrideFunction ? data.overrideMessage(null) : data.functions[name]);
                }
            },
            CallExpression(node) {
                const name = functionName(node.callee);

                if (name === data.overrideFunction) {
                    // `overrideComponentSetup()('sw-product-list', (previousState) => …)`
                    const curried = node.parent.type === 'CallExpression' && node.parent.callee === node ? node.parent : null;

                    api.todo(node, data.overrideMessage(stringValue(curried?.arguments[0])));
                } else if (name && node.callee.type === 'Identifier') {
                    // Calls through a destructured name; member calls are covered by `globals`.
                    api.todo(node, data.functions[name]);
                }
            },
            MemberExpression(node) {
                const isCallee = node.parent.type === 'CallExpression' && node.parent.callee === node;

                if (!isCallee && functionName(node) === data.overrideFunction) {
                    api.todo(node, data.overrideMessage(null));
                }
            },
        };
    },
});
