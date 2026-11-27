/**
 * @sw-package framework
 *
 * Upgrade codemod `cms-helpers`: marks `extractSlotOverrides`, `getCmsPageOverrides` and `deleteSpecifcKeys`
 * everywhere, and `resetCmsPageState` as a method in overrides of `sw-cms-detail` or `sw-cms-create`, as
 * `this.resetCmsPageState()` and in `$super('resetCmsPageState')`. `X.resetCmsPageState()` with any other `X` is the
 * action of the Pinia store `cmsPage`, which stays, and so is `this.resetCmsPageState()` inside the `actions` of a
 * store definition. The texts are in `no-removed-cms-helpers.data.js`.
 */

const { createTodoRule } = require('./upgrade-helpers/create-todo-rule');
const { stringValue } = require('./upgrade-helpers/component-tags');
const { thisMemberName, propertyName } = require('./upgrade-helpers/member-usage');
const data = require('./no-removed-cms-helpers.data');

const RESET = 'resetCmsPageState';

function insideStoreActions(node) {
    for (let current = node.parent; current; current = current.parent) {
        if (current.type === 'Property' && propertyName(current) === 'actions') {
            return true;
        }
    }

    return false;
}

const entryOf = (name) => data.members.find((entry) => entry.name === name);

/** The name of the method `node` sits in, e.g. `resetCmsPageState` for `resetCmsPageState() { … }`. */
function enclosingMethod(node) {
    for (let current = node.parent; current; current = current.parent) {
        if (current.type === 'Property' && ['FunctionExpression', 'ArrowFunctionExpression'].includes(current.value.type)) {
            return propertyName(current);
        }
    }

    return null;
}

module.exports = createTodoRule({
    ...data,
    visitors: (context, api) => ({
        MemberExpression(node) {
            // Inside the target overrides the factory marks it already; the shared line and text keep it to one comment.
            if (thisMemberName(node) === RESET && !insideStoreActions(node)) {
                api.todo(node, entryOf(RESET).message);
            }
        },
        CallExpression(node) {
            if (node.callee.type !== 'MemberExpression' || thisMemberName(node.callee) !== '$super') {
                return;
            }

            const entry = entryOf(stringValue(node.arguments[0]));

            if (!entry) {
                return;
            }

            // `resetCmsPageState() { this.$super('resetCmsPageState'); }`: the comment above the method covers the call.
            const declarationMarked = entry.unique || entry.component.includes(api.targetAt(node));

            if (!declarationMarked || enclosingMethod(node) !== entry.name) {
                api.todo(node, entry.message);
            }
        },
    }),
});
