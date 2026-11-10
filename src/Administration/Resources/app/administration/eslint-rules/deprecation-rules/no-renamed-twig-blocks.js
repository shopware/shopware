/**
 * @sw-package framework
 *
 * Upgrade codemod `twig-block-renames`: renames overrides of Twig blocks that 6.8 renames, where both names render
 * on 6.7 and the new one stays in 6.8. For the other renames no name renders on both versions, so it writes a
 * codemod comment with the 6.8 target. Only `.html.twig` files have blocks; the data is in
 * `no-renamed-twig-blocks.data.js`.
 */

const { reportTodo } = require('./upgrade-helpers/todo-comment');
const { indexByName, forEachBlockOverride, namedEndBlockRange, removalMessage } = require('./utils/twig-block-overrides');
const renames = require('./no-renamed-twig-blocks.data');
const { groups: removals } = require('./no-removed-twig-blocks.data');

const ID = 'twig-block-renames';

const ENTRIES = indexByName(
    renames.map((rename) => ({
        ...rename,
        name: rename.from,
        components: [rename.component],
        shared: rename.shared === true,
        message: rename.to
            ? `Block ${rename.from}${rename.shared ? ` of ${rename.component}` : ''} is renamed to ${rename.to}.`
            : removalMessage({
                name: rename.from,
                component: rename.component,
                shared: rename.shared === true,
                replacement: rename.use ? { use: rename.use, in: rename.in } : { tab: rename.tab, hint: rename.hint },
                flag: rename.flag,
            }),
    })),
);

/** Names the removal codemod reports as shared already; one report per block is enough. */
const REMOVED_NAMES = new Set(removals.flatMap((group) => Object.keys(group.blocks)));

/** @type {import('eslint').Rule.RuleModule} */
module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Rename overrides of Twig blocks that Shopware 6.8 renames',
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
                forEachBlockOverride(context, ENTRIES, (block, entry, file) => {
                    if (!entry.to) {
                        reportTodo(context, { id: ID, message: entry.message, range: block.range, scope: 'template', seen });

                        return;
                    }

                    if (file.names.has(entry.to)) {
                        reportTodo(context, {
                            id: ID,
                            message: `${entry.message.slice(0, -1)}, which this template overrides already. Merge both overrides.`,
                            range: block.range,
                            scope: 'template',
                            seen,
                        });

                        return;
                    }

                    const endName = namedEndBlockRange(file.text, block.range[1], file.comments);

                    context.report({
                        loc: {
                            start: context.sourceCode.getLocFromIndex(block.range[0]),
                            end: context.sourceCode.getLocFromIndex(block.range[1]),
                        },
                        message: entry.message,
                        fix: (fixer) => [
                            fixer.replaceTextRange(block.nameRange, entry.to),
                            ...(endName && file.text.slice(...endName) === entry.from ? [fixer.replaceTextRange(endName, entry.to)] : []),
                        ],
                    });
                }, { reportSharedName: (name) => !REMOVED_NAMES.has(name) });
            },
        };
    },
};
