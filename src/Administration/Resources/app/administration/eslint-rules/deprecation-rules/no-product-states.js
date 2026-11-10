/**
 * @sw-package framework
 *
 * Upgrade codemod `product-states`: 6.8 removes the product states (`is-physical`, `is-download`) in favor of the
 * product type (`physical`, `digital`). The factory marks the members and literals of the data table. The visitors
 * add what the factory cannot see:
 * - the unique names on other objects than `this` (`item.productStates`), as object keys and as template attributes,
 * - `.states` only on product or variant objects and inside overrides of `sw-product*` components,
 * - the state values, unless the same line already has a finding, so `this.productStates.includes('is-download')`
 *   gets one marker comment.
 */

const { createTodoRule } = require('./upgrade-helpers/create-todo-rule');
const { attributeName } = require('./upgrade-helpers/component-tags');
const { propertyName } = require('./upgrade-helpers/member-usage');
const { isTwigFile } = require('./upgrade-helpers/todo-comment');
const data = require('./no-product-states.data');

const UNIQUE = new Map(data.members.filter((entry) => entry.unique).map((entry) => [entry.name, entry.message]));
const STATE_VALUES = new Map(data.stateValues.map((entry) => [entry.value, entry.message]));
const toCamel = (name) => name.replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());

/**
 * The name of the last segment of an object expression: `product` for `this.product`, `this` for `this`.
 *
 * @param {object} node
 * @returns {string|null}
 */
function lastName(node) {
    if (node.type === 'Identifier') {
        return node.name;
    }

    if (node.type === 'ThisExpression') {
        return 'this';
    }

    if (node.type === 'ChainExpression') {
        return lastName(node.expression);
    }

    if (node.type === 'MemberExpression' && !node.computed && node.property.type === 'Identifier') {
        return node.property.name;
    }

    return null;
}

function isProductStates(node, targets) {
    const { objects, targetPrefix, exclude } = data.statesProperty;
    const objectName = lastName(node.object);

    if (objectName && objects.test(objectName)) {
        return true;
    }

    return targets.some((target) => target?.startsWith(targetPrefix)) && !(objectName && exclude.test(objectName));
}

function visitorsFor(scope) {
    return (context, api) => {
        const flaggedLines = new Set();
        const record = (node) => flaggedLines.add(node.loc.start.line);
        const report = (node, message) => {
            record(node);
            api.todo(node, message, scope);
        };
        const targetsOf = (node) => {
            if (scope === 'script') {
                return [api.targetAt(node)];
            }

            return isTwigFile(context) ? api.twigTargets() : api.fileTargets();
        };

        const visitors = {
            MemberExpression(node) {
                if (node.computed || node.property.type !== 'Identifier') {
                    return;
                }

                const name = node.property.name;

                if (UNIQUE.has(name)) {
                    // `this.<name>` is marked by the factory already.
                    if (node.object.type === 'ThisExpression') {
                        record(node);
                    } else {
                        report(node, UNIQUE.get(name));
                    }

                    return;
                }

                if (name === 'states' && isProductStates(node, targetsOf(node))) {
                    report(node, data.statesProperty.message);
                }
            },
            Property(node) {
                const name = propertyName(node);

                if (UNIQUE.has(name)) {
                    report(node, UNIQUE.get(name));
                }
            },
            Literal(node) {
                const message = typeof node.value === 'string' ? STATE_VALUES.get(node.value) : undefined;

                if (message && !flaggedLines.has(node.loc.start.line)) {
                    report(node, message);
                }
            },
        };

        if (scope === 'template') {
            // Template references are marked by the factory; only remember their lines.
            visitors.Identifier = (node) => {
                if (UNIQUE.has(node.name)) {
                    record(node);
                }
            };
            visitors.VAttribute = (node) => {
                const name = attributeName(node);
                const message = name ? UNIQUE.get(toCamel(name)) : undefined;

                if (message) {
                    report(node, message);
                }
            };
        }

        return visitors;
    };
}

module.exports = createTodoRule({
    ...data,
    visitors: visitorsFor('script'),
    templateVisitors: visitorsFor('template'),
});
