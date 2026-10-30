/**
 * @sw-package framework
 */

const fs = require('fs');
const path = require('path');

const isMiniModuleRoot = (directory) => ['index.ts', 'src', 'tests'].every((entry) => fs.existsSync(path.join(directory, entry)));

/**
 * Tells which part of a mini-module a file is, for the markers its exports need.
 * A mini-module is a directory with an `index.ts`, a `src/` and a `tests/` folder.
 * Its `index.ts` is public API, also for extensions, and every export there is marked `@public`.
 * Its `private.ts` is for Administration code only, and every export there is marked `@private`.
 * Its `register.ts` registers what `index.ts` declares, so it needs no marker either.
 * Its `src/` is only reachable through `index.ts` and `private.ts`, so its exports carry no marker:
 * an export marked `@public` belongs in `index.ts`, one marked `@private` in `private.ts`.
 *
 * @example
 * getMiniModulePart('src/module/sw-order/page/sw-order-list/index.ts') // → 'index'
 * getMiniModulePart('src/app/main.ts') // → null
 *
 * @param {string} filename
 * @returns {'index'|'private'|'register'|'src'|null}
 */
const getMiniModulePart = (filename) => {
    const entry = /^(index|private|register)\.[jt]s$/.exec(path.basename(filename));
    if (entry && isMiniModuleRoot(path.dirname(filename))) {
        return entry[1];
    }

    const segments = filename.split(path.sep);
    const sourceIndex = segments.lastIndexOf('src');

    return sourceIndex > 0 && isMiniModuleRoot(segments.slice(0, sourceIndex).join(path.sep)) ? 'src' : null;
};

const getLeadingBlockComment = (node) => {
    const comments = findComments(node);
    const leadingComment = comments?.find(c => c.loc.end.line === (node.loc.start.line - 1));

    return leadingComment?.type === 'Block' ? leadingComment.value : null;
};

const nodeContainsLeadingBlockComment = (node) => {
    const comment = getLeadingBlockComment(node);

    return comment !== null && (comment.includes('@private') || comment.includes('@deprecated tag:'));
};

function findComments(node) {
    while (node.comments === undefined && node.parent) {
        node = node.parent;
    }

    return node.comments;
}

/**
 * This rule validates that new features are either private or deprecated to be private in the future.
 * The index.ts of a mini-module is the exception: its exports are marked @public.
 *
 * Invalid:
 * class Example {}
 * Component.register('foo', {});
 * Shopware.Component.register('bar', {});
 * Module.register('foo', {});
 * Shopware.Module.register('bar', {});
 * export const foo = 'foo';
 * export default foo;
 *
 * Valid:
 * \**
 *  * @private
 *  *\
 * class Example {}
 *
 * \**
 *  * @deprecated tag:v6.X.0 - Will be @private
 *  *\
 * class Example {}
 *
 * \**
 *  * @private
 *  *\
 * Component.register('foo', {});
 *
 * \**
 *  * @deprecated tag:v6.X.0 - Will be @private
 *  *\
 * Shopware.Component.register('bar', {});
 *
 * \**
 *  * @private
 *  *\
 * Module.register('foo', {});
 *
 * \**
 *  * @deprecated tag:v6.X.0 - Will be @private
 *  *\
 * Shopware.Module.register('bar', {});
 *
 * \**
 *  * @private
 *  *\
 * export const foo = 'foo';
 *
 * \**
 *  * @deprecated tag:v6.X.0 - Will be @private
 *  *\
 * export default foo;
 */
/** @type {import('eslint').Rule.RuleModule} */
module.exports = {
    meta: {
        type: 'problem',

        docs: {
            description: 'New features have to be private',
            recommended: true,
            url: 'https://handbook.shopware.com/Product/Product/Components/Admin/NewFeatures',
        },
    },
    create(context) {
        const part = getMiniModulePart(context.filename ?? context.getFilename());

        if (part === 'register') {
            return {};
        }

        const checkExport = (node) => {
            const comment = getLeadingBlockComment(node) ?? '';
            const isPublic = comment.includes('@public');
            const isPrivate = comment.includes('@private');

            if (part === 'src') {
                if (isPublic || isPrivate) {
                    context.report({ node, message: 'Exports in the src/ of a mini-module carry no marker. Export @public API from index.ts and @private API from private.ts.' });
                }

                return;
            }

            if (part === 'index') {
                if (!isPublic || isPrivate) {
                    context.report({ node, message: 'The index.ts of a mini-module is public API. Mark this export @public, or move it to private.ts.' });
                }

                return;
            }

            if (part === 'private') {
                if (!isPrivate || isPublic) {
                    context.report({ node, message: 'The private.ts of a mini-module never reaches extensions. Mark this export @private, or move it to index.ts.' });
                }

                return;
            }

            if (isPublic) {
                context.report({ node, message: 'Only the index.ts of a mini-module may export @public API.' });

                return;
            }

            if (!nodeContainsLeadingBlockComment(node)) {
                context.report({
                    node,
                    message: 'New exports need to be private. Old exports should be @deprecated tag:v6.X.0 - Will be private',
                });
            }
        };

        return {
            ExportDefaultDeclaration: checkExport,
            ExportNamedDeclaration: checkExport,
            ExportAllDeclaration(node) {
                if (part === 'index' || part === 'private') {
                    checkExport(node);
                }
            },
            ExpressionStatement(node) {
                const root = node.expression?.callee?.object;
                const rootObject = root?.name;
                const property = node.expression?.callee?.property?.name;

                if (!property || property !== 'register') {
                    return;
                }

                let isFeatureRegister = false;

                if (rootObject === 'Component' && property === 'register') {
                    isFeatureRegister = true;
                }

                if (!isFeatureRegister && root?.object && root?.property && root?.object?.name === 'Shopware' && root.property.name === 'Component' && property === 'register') {
                    isFeatureRegister = true;
                }

                if (!isFeatureRegister && rootObject === 'Module' && property === 'register') {
                    isFeatureRegister = true;
                }

                if (!isFeatureRegister && root?.object && root?.property && root?.object?.name === 'Shopware' && root.property.name === 'Module' && property === 'register') {
                    isFeatureRegister = true;
                }

                if (!isFeatureRegister && rootObject === 'Service' && property === 'register') {
                    isFeatureRegister = true;
                }

                if (!isFeatureRegister && root?.callee?.object && root?.callee?.property && root?.callee?.object.name === 'Shopware' && root.callee.property.name === 'Service' && property === 'register') {
                    isFeatureRegister = true;
                }

                if (!isFeatureRegister || nodeContainsLeadingBlockComment(node)) {
                    return;
                }

                context.report({
                    node,
                    message: 'New features need to be private. Old features should be @deprecated tag:v6.X.0 - Will be private',
                });
            },
        };
    },
};
