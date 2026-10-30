/**
 * @sw-package framework
 */

const path = require('path');
const { findMiniModule } = require('./mini-module');

/** Imports that only add a template or styles to the bundle. */
const ASSET_IMPORT = /\.(s?css|twig)$/;

/**
 * Calls that look something up by its registered name: `Shopware.Service('x')`, `Shopware.Store.get('x')`
 * and `Shopware.Mixin.getByName('x')`, also without the `Shopware.` prefix.
 */
const NAMED_LOOKUPS = ['Service', 'Store.get', 'Mixin.getByName'];

function getCalleeName(callee) {
    if (callee.type === 'Identifier') {
        return callee.name;
    }

    if (callee.type === 'MemberExpression' && !callee.computed) {
        const object = getCalleeName(callee.object);

        return object ? `${object}.${callee.property.name}` : null;
    }

    return null;
}

function isNamedLookup(node) {
    const name = getCalleeName(node.callee)?.replace(/^Shopware\./, '');

    return NAMED_LOOKUPS.includes(name);
}

function isInsideFunction(node) {
    for (let parent = node.parent; parent; parent = parent.parent) {
        if (['FunctionDeclaration', 'FunctionExpression', 'ArrowFunctionExpression'].includes(parent.type)) {
            return true;
        }
    }

    return false;
}

module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'The src/ of a mini-module reaches outside only through its dependencies.ts',
        },
        messages: {
            globalInSrc: "src/ reaches 'Shopware' only through '../dependencies'.",
            importInSrc: "src/ imports from outside the module only through '../dependencies'.",
            stringName:
                'Import this name instead of writing it as a string, so a renamed {{kind}} fails the build, not the page.',
            callAtLoad: 'dependencies.ts has no side effects. Wrap this call in a function that runs it when it is used.',
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

        if (!miniModule || !['src', 'dependencies'].includes(miniModule.part)) {
            return {};
        }

        const reportStringNames = {
            CallExpression(node) {
                if (isNamedLookup(node) && node.arguments[0]?.type === 'Literal') {
                    context.report({
                        node: node.arguments[0],
                        messageId: 'stringName',
                        data: { kind: 'service, store or mixin' },
                    });
                }
            },
            'Property[key.name="inject"] > ArrayExpression > Literal'(node) {
                context.report({ node, messageId: 'stringName', data: { kind: 'service' } });
            },
        };

        if (miniModule.part === 'dependencies') {
            return {
                ...reportStringNames,
                'Program > ExportNamedDeclaration CallExpression'(node) {
                    if (!isInsideFunction(node)) {
                        context.report({ node, messageId: 'callAtLoad' });
                    }
                },
            };
        }

        const moduleRoot = path.posix.join(cwd.split(path.sep).join('/'), miniModule.root);

        return {
            ...reportStringNames,
            'Identifier[name="Shopware"]'(node) {
                context.report({ node, messageId: 'globalInSrc' });
            },
            ImportDeclaration(node) {
                const source = node.source.value;

                if (ASSET_IMPORT.test(source)) {
                    return;
                }

                const target = source.startsWith('.')
                    ? path.posix.resolve(
                          path.posix.dirname(path.posix.join(cwd.split(path.sep).join('/'), filename)),
                          source,
                      )
                    : null;

                if (target === `${moduleRoot}/dependencies` || target?.startsWith(`${moduleRoot}/src/`)) {
                    return;
                }

                context.report({ node: node.source, messageId: 'importInSrc' });
            },
        };
    },
};
