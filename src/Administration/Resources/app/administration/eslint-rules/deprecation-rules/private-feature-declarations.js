/**
 * @sw-package framework
 */

const { createJiti } = require('jiti');

const { isEslintDirective } = createJiti(__filename)('./utils/eslint-directive.ts');

/**
 * The comment on the line above `node`. ESLint directives in between are skipped,
 * so disabling another rule for the declaration keeps its declaration comment valid.
 */
function findLeadingComment(node) {
    const comments = findComments(node) ?? [];
    let line = node.loc.start.line;
    let comment;

    do {
        comment = comments.find(c => c.loc.end.line === (line - 1));
        line = comment?.loc.start.line;
    } while (comment && isEslintDirective(comment.value));

    return comment;
}

const nodeContainsLeadingBlockComment = (node) => {
    const leadingComment = findLeadingComment(node);

    if (!leadingComment) {
        return false;
    }

    if (leadingComment.type !== 'Block') {
        return false;
    }

    const isJsDoc = leadingComment.value.startsWith('*');

    return leadingComment.value.includes('@private') ||
        leadingComment.value.includes('@deprecated tag:') ||
        leadingComment.value.includes('@bc-change BecomesPrivate') ||
        (isJsDoc && leadingComment.value.includes('@public'));
};

function findComments(node) {
    while (node.comments === undefined && node.parent) {
        node = node.parent;
    }

    return node.comments;
}

/**
 * This rule validates that new features are either private, announced to become private in the future
 * or explicitly marked as public API with a JSDoc `@public` tag.
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
 *  * @bc-change BecomesPrivate version:v6.8.0
 *  *\
 * class Example {}
 *
 * \**
 *  * @private
 *  *\
 * Component.register('foo', {});
 *
 * \**
 *  * @bc-change BecomesPrivate version:v6.8.0
 *  *\
 * Shopware.Component.register('bar', {});
 *
 * \**
 *  * @private
 *  *\
 * Module.register('foo', {});
 *
 * \**
 *  * @bc-change BecomesPrivate version:v6.8.0
 *  *\
 * Shopware.Module.register('bar', {});
 *
 * \**
 *  * @private
 *  *\
 * export const foo = 'foo';
 *
 * \**
 *  * @bc-change BecomesPrivate version:v6.8.0
 *  *\
 * export default foo;
 *
 * \**
 *  * @public
 *  *\
 * export const foo = 'foo';
 *
 * \**
 *  * @public
 *  *\
 * Component.register('foo', {});
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
        return {
            ExportDefaultDeclaration(node) {
                if (nodeContainsLeadingBlockComment(node)) {
                    return;
                }

                context.report({
                    node,
                    message: 'New exports need to be private. Old exports should be marked @bc-change BecomesPrivate version:vX.Y.Z',
                });
            },
            ExportNamedDeclaration(node) {
                if (nodeContainsLeadingBlockComment(node)) {
                    return;
                }

                context.report({
                    node,
                    message: 'New exports need to be private. Old exports should be marked @bc-change BecomesPrivate version:vX.Y.Z',
                });
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
                    message: 'New features need to be private. Old features should be marked @bc-change BecomesPrivate version:vX.Y.Z',
                });
            },
        };
    },
};
