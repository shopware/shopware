/**
 * @sw-package framework
 */

const { validateShopwareSetupSfc, ShopwareSetupTransformError } = require('../../build/vue-setup-transform');

const LOCAL_WITH_DEFAULTS_MESSAGE = 'withDefaults() defaults must not reference local setup bindings in Shopware setup blocks. Use inline literals, imported constants, or destructured defineProps() defaults instead.';

/**
 * Checks whether this Vue file contains a base Shopware setup block.
 *
 * @param {string} source
 * @returns {boolean}
 */
function hasBaseShopwareSetupBlock(source) {
    return /<script\b(?=[^>]*\bsetup\b)(?=[^>]*\bsw-component\s*=)[^>]*>/u.test(source);
}

/**
 * Adds all identifiers declared by one binding pattern to a set.
 *
 * @param {import('estree').Node | null | undefined} pattern
 * @param {Set<string>} names
 * @returns {void}
 */
function collectPatternNames(pattern, names) {
    if (!pattern) {
        return;
    }

    if (pattern.type === 'Identifier') {
        names.add(pattern.name);
        return;
    }

    if (pattern.type === 'RestElement') {
        collectPatternNames(pattern.argument, names);
        return;
    }

    if (pattern.type === 'AssignmentPattern') {
        collectPatternNames(pattern.left, names);
        return;
    }

    if (pattern.type === 'ArrayPattern') {
        pattern.elements.forEach((element) => collectPatternNames(element, names));
        return;
    }

    if (pattern.type === 'ObjectPattern') {
        pattern.properties.forEach((property) => {
            if (property.type === 'RestElement') {
                collectPatternNames(property.argument, names);
                return;
            }

            collectPatternNames(property.value, names);
        });
    }
}

/**
 * Collects top-level setup bindings that are local to the SFC.
 *
 * @param {import('estree').Program} ast
 * @returns {Set<string>}
 */
function collectTopLevelLocalBindings(ast) {
    const names = new Set();

    ast.body.forEach((statement) => {
        if (statement.type === 'VariableDeclaration') {
            statement.declarations.forEach((declaration) => collectPatternNames(declaration.id, names));
            return;
        }

        if (
            (statement.type === 'FunctionDeclaration' || statement.type === 'ClassDeclaration') &&
            statement.id?.name
        ) {
            names.add(statement.id.name);
        }
    });

    return names;
}

/**
 * Walks ESTree nodes and calls a visitor for each node.
 *
 * @param {unknown} node
 * @param {(node: import('estree').Node, parent: import('estree').Node | null, parentKey: string | null) => boolean | void} visitor
 * @param {import('estree').Node | null} [parent]
 * @param {string | null} [parentKey]
 * @returns {void}
 */
function walk(node, visitor, parent = null, parentKey = null) {
    if (!node || typeof node !== 'object' || typeof node.type !== 'string') {
        return;
    }

    if (visitor(node, parent, parentKey) === false) {
        return;
    }

    Object.entries(node).forEach(([key, value]) => {
        if (
            key === 'parent' ||
            key === 'loc' ||
            key === 'range' ||
            key === 'tokens' ||
            key === 'comments' ||
            key === 'typeAnnotation' ||
            key === 'typeParameters' ||
            key === 'returnType' ||
            key === 'typeArguments'
        ) {
            return;
        }

        if (Array.isArray(value)) {
            value.forEach((child) => walk(child, visitor, node, key));
            return;
        }

        walk(value, visitor, node, key);
    });
}

/**
 * Checks whether an identifier is read as a runtime value.
 *
 * @param {import('estree').Identifier} node
 * @param {import('estree').Node | null} parent
 * @param {string | null} parentKey
 * @returns {boolean}
 */
function isIdentifierReference(node, parent, parentKey) {
    if (!parent) {
        return true;
    }

    if (parent.type === 'MemberExpression') {
        return parentKey !== 'property' || Boolean(parent.computed);
    }

    if (parent.type === 'Property') {
        return parentKey !== 'key' || Boolean(parent.computed) || Boolean(parent.shorthand);
    }

    if (
        parent.type === 'VariableDeclarator' ||
        parent.type === 'FunctionDeclaration' ||
        parent.type === 'FunctionExpression' ||
        parent.type === 'ClassDeclaration' ||
        parent.type === 'ClassExpression'
    ) {
        return parentKey !== 'id';
    }

    if (parentKey === 'params') {
        return false;
    }

    return true;
}

/**
 * Reports local setup binding references in `withDefaults(..., defaults)`.
 *
 * @param {import('eslint').Rule.RuleContext} context
 * @param {import('estree').Program} ast
 * @returns {void}
 */
function reportLocalWithDefaultsReferences(context, ast) {
    const localBindings = collectTopLevelLocalBindings(ast);

    walk(ast, (node) => {
        if (
            node.type !== 'CallExpression' ||
            node.callee.type !== 'Identifier' ||
            node.callee.name !== 'withDefaults'
        ) {
            return;
        }

        const defaults = node.arguments[1];
        const reportedNames = new Set();

        walk(defaults, (child, parent, parentKey) => {
            if (child.type !== 'Identifier') {
                return;
            }

            if (!localBindings.has(child.name) || reportedNames.has(child.name)) {
                return;
            }

            if (!isIdentifierReference(child, parent, parentKey)) {
                return;
            }

            reportedNames.add(child.name);
            context.report({
                node: child,
                message: LOCAL_WITH_DEFAULTS_MESSAGE,
            });
        });
    });
}

/**
 * Reports shared transform errors through ESLint so editor feedback matches build behavior.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Validate Shopware setup SFC blocks',
            category: 'Possible Errors',
            recommended: true,
        },
        schema: [],
    },

    create(context) {
        return {
            Program(node) {
                const filename = context.getFilename();

                if (!filename.endsWith('.vue')) {
                    return;
                }

                const sourceCode = context.getSourceCode();

                try {
                    validateShopwareSetupSfc(sourceCode.text, filename);
                } catch (error) {
                    if (!(error instanceof ShopwareSetupTransformError)) {
                        throw error;
                    }

                    context.report({
                        node,
                        loc: sourceCode.getLocFromIndex(Math.min(error.index, sourceCode.text.length)),
                        message: error.message,
                    });
                }

                if (hasBaseShopwareSetupBlock(sourceCode.text)) {
                    reportLocalWithDefaultsReferences(context, sourceCode.ast);
                }
            },
        };
    },
};
