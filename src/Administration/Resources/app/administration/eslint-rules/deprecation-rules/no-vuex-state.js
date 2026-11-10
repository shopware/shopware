/**
 * @sw-package framework
 *
 * Upgrade codemod `vuex-state`: 6.8 removes Vuex. The rule marks `Shopware.State` (also through
 * `const { State } = Shopware`), `Shopware.Application.getContainer('factory').state`, `$store` in scripts and
 * templates, the `mapVuex*` component helpers, imports of `vuex` and `VuexRootState`. The text depends on the
 * form: `get('<id>')` names the Pinia store id, `getters`, `commit`, `dispatch`, `registerModule`, `watch` and the
 * subscriptions name their Pinia counterpart. The texts are in `no-vuex-state.data.js`.
 */

const { createTodoRule } = require('./upgrade-helpers/create-todo-rule');
const { stringValue } = require('./upgrade-helpers/component-tags');
const data = require('./no-vuex-state.data');

/**
 * The key of a member access: `a.b` and `a['b']` → `b`.
 *
 * @param {object} node MemberExpression
 * @returns {string|null}
 */
function propertyKey(node) {
    if (node.type !== 'MemberExpression') {
        return null;
    }

    return !node.computed && node.property.type === 'Identifier' ? node.property.name : stringValue(node.property);
}

function isObjectOf(parent, node) {
    return parent?.type === 'MemberExpression' && parent.object === node;
}

function isCalleeOf(parent, node) {
    return parent?.type === 'CallExpression' && parent.callee === node;
}

/** `.flow` of `Shopware.State.get('swFlowState').flow`. */
function restOf(node) {
    return isObjectOf(node.parent, node) && !node.parent.computed && node.parent.property.type === 'Identifier'
        ? `.${node.parent.property.name}`
        : '';
}

/** `swFlowState/setFlow` → `{ module: 'swFlowState', name: 'setFlow' }`. */
function splitPath(path) {
    const index = path ? path.lastIndexOf('/') : -1;

    return index > 0 ? { module: path.slice(0, index), name: path.slice(index + 1) } : { module: null, name: path ?? null };
}

/**
 * The form of a Vuex access `<root>.<key>…`.
 *
 * @param {object} access MemberExpression whose object is the Vuex root
 * @returns {object} see `stateMessage()` in the data file
 */
function shapeOf(access) {
    const key = propertyKey(access);
    const call = isCalleeOf(access.parent, access) ? access.parent : null;
    const argument = call ? stringValue(call.arguments[0]) : null;

    switch (key) {
        case 'get':
            return call ? { kind: 'get', module: argument, rest: restOf(call) } : { kind: 'other' };
        case 'state':
            // `$store.state.swFlowState.flow`
            return isObjectOf(access.parent, access)
                ? { kind: 'get', module: propertyKey(access.parent), rest: restOf(access.parent) }
                : { kind: 'other' };
        case 'getters':
            return { kind: 'getter', ...splitPath(isObjectOf(access.parent, access) ? propertyKey(access.parent) : null) };
        case 'commit':
        case 'dispatch':
            return { kind: key, ...splitPath(argument) };
        case 'registerModule':
        case 'unregisterModule':
            return { kind: key, module: argument };
        case 'watch':
        case 'subscribe':
        case 'subscribeAction':
        case 'list':
            return { kind: key };
        default:
            return { kind: 'other' };
    }
}

function isFactoryContainer(node) {
    const callee = node?.type === 'CallExpression' ? node.callee : null;

    return (
        callee?.type === 'MemberExpression' &&
        propertyKey(callee) === 'getContainer' &&
        stringValue(node.arguments[0]) === 'factory'
    );
}

module.exports = createTodoRule({
    ...data,
    visitors: (context, api) => {
        /** How `node` reaches Vuex, or `null`. */
        const vuexRoot = (node) => {
            const path = api.memberPath(node);

            if (path === 'Shopware.State' || path === 'this.$store') {
                return path;
            }

            if (node.type === 'MemberExpression' && propertyKey(node) === 'state' && isFactoryContainer(node.object)) {
                return "Shopware.Application.getContainer('factory').state";
            }

            return null;
        };

        return {
            MemberExpression(node) {
                const objectRoot = vuexRoot(node.object);

                if (objectRoot) {
                    api.todo(node, data.stateMessage(objectRoot, shapeOf(node)));

                    return;
                }

                // `const State = Shopware.State`, `const { get } = Shopware.State`, `register(this.$store)`
                const root = vuexRoot(node);

                if (root && !isObjectOf(node.parent, node)) {
                    api.todo(node, data.stateMessage(root, { kind: 'other' }));
                }
            },
            VariableDeclarator(node) {
                if (node.id.type !== 'ObjectPattern' || node.init?.type !== 'Identifier' || node.init.name !== 'Shopware') {
                    return;
                }

                for (const property of node.id.properties) {
                    if (property.type === 'Property' && !property.computed && property.key.name === 'State') {
                        api.todo(property, data.stateMessage('Shopware.State', { kind: 'other' }));
                    }
                }
            },
            CallExpression(node) {
                const callee = node.callee.type === 'Identifier' ? node.callee.name : propertyKey(node.callee);

                if (Object.hasOwn(data.mapHelpers, callee ?? '')) {
                    api.todo(node, data.mapHelperMessage(callee, stringValue(node.arguments[0])));
                }
            },
            'ObjectPattern > Property'(node) {
                const name = !node.computed && node.key.type === 'Identifier' ? node.key.name : null;

                if (Object.hasOwn(data.mapHelpers, name ?? '')) {
                    api.todo(node, data.mapHelperMessage(name, null));
                }
            },
            TSInterfaceDeclaration(node) {
                if (node.id.name === 'VuexRootState') {
                    api.todo(node, data.rootStateMessage);
                }
            },
        };
    },
    templateVisitors: (context, api) => ({
        // In `.html.twig` files the parser keeps `{{ … }}` as Twig text, so `$store` there is only visible in the text.
        VText(node) {
            const text = context.sourceCode.text.slice(node.range[0], node.range[1]);

            for (const match of text.matchAll(/\{\{[^}]*?(?<![\w$.])(\$store\b[\w$.[\]'"/-]*)/g)) {
                const offset = node.range[0] + match.index + match[0].length - match[1].length;
                const [, stateModule, stateRest] = /^\$store\.state\.([\w$]+)(\.[\w$]+)?/.exec(match[1]) ?? [];
                const [, getter] = /^\$store\.getters\[['"]([^'"]+)['"]\]/.exec(match[1]) ?? [];
                let shape = { kind: 'other' };

                if (stateModule) {
                    shape = { kind: 'get', module: stateModule, rest: stateRest ?? '' };
                } else if (getter) {
                    shape = { kind: 'getter', ...splitPath(getter) };
                }

                api.todo({ range: [offset, offset + match[1].length] }, data.stateMessage('$store', shape), 'template');
            }
        },
        Identifier(node) {
            const parent = node.parent;

            if (node.name !== '$store' || (parent.type === 'MemberExpression' && parent.property === node && !parent.computed)) {
                return;
            }

            const shape = isObjectOf(parent, node) ? shapeOf(parent) : { kind: 'other' };

            api.todo(node, data.stateMessage('$store', shape), 'template');
        },
    }),
});
