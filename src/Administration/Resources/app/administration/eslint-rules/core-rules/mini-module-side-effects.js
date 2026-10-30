/**
 * @sw-package framework
 */

const path = require('path');
const { findMiniModule } = require('./mini-module');

/** Imports that only add a template or styles to the bundle, which the component needs to work. */
const ASSET_IMPORT = /\.(s?css|twig)$/;

module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Only the register.ts of a mini-module has side effects',
        },
        messages: {
            sideEffect:
                'Only the register.ts of a mini-module may have side effects. Move this {{what}} there, or make it lazy.',
        },
        schema: [],
    },
    create(context) {
        const cwd = context.cwd ?? process.cwd();
        const filename = path
            .relative(cwd, context.filename ?? context.getFilename())
            .split(path.sep)
            .join('/');
        const miniModule = findMiniModule(filename, cwd);

        if (
            !miniModule ||
            ![
                'index',
                'private',
                'dependencies',
                'src',
            ].includes(miniModule.part)
        ) {
            return {};
        }

        return {
            'Program > ExpressionStatement'(node) {
                if (node.directive) {
                    return;
                }

                context.report({ node, messageId: 'sideEffect', data: { what: 'top-level statement' } });
            },
            'Program > ImportDeclaration'(node) {
                if (node.specifiers.length > 0 || ASSET_IMPORT.test(node.source.value)) {
                    return;
                }

                context.report({ node, messageId: 'sideEffect', data: { what: 'side-effect import' } });
            },
        };
    },
};
