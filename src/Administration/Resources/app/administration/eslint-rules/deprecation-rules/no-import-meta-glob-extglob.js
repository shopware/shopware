/**
 * @sw-package framework
 */

const { hasExtglob, toNegatedGlobPatterns } = require('./utils/negated-glob-patterns');

function isImportMetaGlobCall(node) {
    const callee = node.callee;

    return callee.type === 'MemberExpression'
        && callee.computed === false
        && callee.object.type === 'MetaProperty'
        && callee.object.meta.name === 'import'
        && callee.object.property.name === 'meta'
        && callee.property.type === 'Identifier'
        && callee.property.name === 'glob';
}

function getStaticString(node) {
    if (node?.type === 'Literal' && typeof node.value === 'string') {
        return node.value;
    }

    if (node?.type === 'TemplateLiteral' && node.expressions.length === 0) {
        return node.quasis[0].value.cooked;
    }

    return null;
}

function toStringLiteral(node, value) {
    const quote = node.type === 'TemplateLiteral' ? '`' : node.raw[0];
    const escaped = value.replace(/\\/g, '\\\\').replaceAll(quote, `\\${quote}`);

    return `${quote}${escaped}${quote}`;
}

/** @type {import('eslint').Rule.RuleModule} */
module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Disallow extglob patterns in import.meta.glob, which Vite 8 does not match',
            category: 'Vite 8 Migration',
            recommended: true,
        },
        fixable: 'code',
        schema: [],
        messages: {
            rewrite: 'Vite 8, the default from Shopware 6.8 on, does not match the extglob pattern "{{ pattern }}" in import.meta.glob. Use {{ replacement }} instead.',
            manual: 'Vite 8, the default from Shopware 6.8 on, does not match the extglob pattern "{{ pattern }}" in import.meta.glob. Replace it with negated patterns.',
        },
    },
    create(context) {
        return {
            CallExpression(node) {
                const [globs] = node.arguments;

                if (!globs || !isImportMetaGlobCall(node)) {
                    return;
                }

                const elements = globs.type === 'ArrayExpression' ? globs.elements : [globs];

                elements.forEach((element) => {
                    const pattern = getStaticString(element);

                    if (pattern === null || !hasExtglob(pattern)) {
                        return;
                    }

                    const patterns = toNegatedGlobPatterns(pattern);

                    if (patterns.some(hasExtglob)) {
                        context.report({ node: element, messageId: 'manual', data: { pattern } });
                        return;
                    }

                    const replacement = patterns.map((rewritten) => toStringLiteral(element, rewritten)).join(', ');

                    context.report({
                        node: element,
                        messageId: 'rewrite',
                        data: { pattern, replacement: `[${replacement}]` },
                        fix: (fixer) => fixer.replaceText(element, element === globs ? `[${replacement}]` : replacement),
                    });
                });
            },
        };
    },
};
