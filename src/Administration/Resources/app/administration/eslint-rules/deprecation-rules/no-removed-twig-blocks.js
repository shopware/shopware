/**
 * @sw-package framework
 *
 * Upgrade codemod `twig-block-removals`: writes a codemod comment above every override of a Twig block that 6.8 removes,
 * naming the block to override instead. Only `.html.twig` files have blocks; the data is in
 * `no-removed-twig-blocks.data.js`.
 */

const { reportTodo } = require('./upgrade-helpers/todo-comment');
const { indexByName, forEachBlockOverride, removalMessage } = require('./utils/twig-block-overrides');
const { groups, shared } = require('./no-removed-twig-blocks.data');

const ID = 'twig-block-removals';

function why(group) {
    if (group.reason === 'tabs') {
        return ' with the legacy sw-tabs branch';
    }

    return group.reason === 'component' ? ` with the ${group.component} component` : '';
}

const ENTRIES = indexByName(
    groups.flatMap((group) => Object.entries(group.blocks).map(([name, replacement]) => ({
        name,
        component: group.component,
        components: [group.component, ...group.inherited],
        shared: Object.hasOwn(shared, name),
        message: removalMessage({
            name,
            component: group.component,
            shared: Object.hasOwn(shared, name),
            why: why(group),
            replacement,
        }),
    }))),
);

/** @type {import('eslint').Rule.RuleModule} */
module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Mark overrides of Twig blocks that Shopware 6.8 removes',
            category: 'Shopware 6.8 Migration',
            recommended: false,
        },
        fixable: 'code',
        schema: [],
    },
    create(context) {
        const seen = new Set();

        return {
            Program() {
                forEachBlockOverride(context, ENTRIES, (block, entry) => {
                    reportTodo(context, { id: ID, message: entry.message, range: block.range, scope: 'template', seen });
                });
            },
        };
    },
};
