/**
 * @sw-package framework
 */
const { readFileSync } = require('node:fs');
const path = require('node:path');
const { parse } = require('yaml');

const featureConfig = parse(readFileSync(path.resolve(__dirname, '../../../../../../Core/Framework/Resources/config/packages/feature.yaml'), 'utf8'));
const registeredFlags = new Set(featureConfig.shopware.feature.flags.map(({ name }) => name));

module.exports = {
    meta: {
        type: 'problem',
        docs: { description: 'Require runtime guards in deprecated Storefront functions and methods' },
        schema: [],
        messages: {
            missingGuard: 'Deprecated callable must contain triggerDeprecationOrThrow("{{ flag }}", message). Conditional guards are allowed.',
            unknownFlag: 'Deprecation refers to unregistered removal flag "{{ flag }}".',
            moduleGuard: 'Trigger deprecations when the deprecated API is used, not during module evaluation.',
        },
    },

    create(context) {
        const sourceCode = context.sourceCode;
        const functions = [];

        function getDeprecation(node) {
            const comment = sourceCode.getCommentsBefore(node).findLast(({ type, value }) => type === 'Block' && value.startsWith('*'));
            if (!comment || /(?:^|\n)\s*\*?\s*@private\b/.test(comment.value)) {
                return null;
            }

            const version = comment.value.match(/(?:^|\n)\s*\*?\s*@deprecated\s+tag:(v\d+\.\d+\.\d+(?:\.\d+)?)\b/);
            return version ? `${version[1]}${version[1].split('.').length === 3 ? '.0' : ''}` : null;
        }

        function getDeclaration(node) {
            if (['MethodDefinition', 'Property', 'VariableDeclarator'].includes(node.parent.type)) {
                node = node.parent;
            }
            if (node.type === 'VariableDeclarator') {
                node = node.parent;
                if (node.declarations.length !== 1) {
                    return null;
                }
            }
            if (['ExportDefaultDeclaration', 'ExportNamedDeclaration'].includes(node.parent.type)) {
                return node.parent;
            }
            return node;
        }

        function isFeatureGuard(node) {
            const callee = node.callee;
            if (callee.type !== 'MemberExpression' || callee.computed || callee.property.name !== 'triggerDeprecationOrThrow') {
                return false;
            }
            const receiver = callee.object;
            const references = sourceCode.getScope(node).references;
            if (receiver.type === 'Identifier') {
                const variable = references.find(({ identifier }) => identifier === receiver)?.resolved;
                return variable?.defs.some(({ type, node: declaration, parent }) => type === 'ImportBinding' &&
                    declaration.type === 'ImportDefaultSpecifier' && /\bhelper\/feature\.helper(?:\.js)?$/.test(parent.source.value));
            }
            if (receiver.type === 'MemberExpression' && !receiver.computed && receiver.object.name === 'window' && receiver.property.name === 'Feature') {
                const variable = references.find(({ identifier }) => identifier === receiver.object)?.resolved;
                return !variable?.defs.length;
            }
            return false;
        }

        return {
            ':function'(node) {
                const declaration = getDeclaration(node);
                let flag = declaration && getDeprecation(declaration);
                if (!flag && node.parent.type === 'MethodDefinition' && node.parent.key.name === 'init') {
                    const classNode = node.parent.parent.parent;
                    flag = getDeprecation(classNode) || getDeprecation(classNode.parent);
                }
                functions.push({ node, flag, guarded: false });
            },

            CallExpression(node) {
                if (!isFeatureGuard(node)) {
                    return;
                }
                const current = functions.at(-1);
                if (!current) {
                    context.report({ node, messageId: 'moduleGuard' });
                    return;
                }
                const [flag, message] = node.arguments;
                if (flag?.type === 'Literal' && flag.value === current.flag && message &&
                    (message.type !== 'Literal' || (typeof message.value === 'string' && message.value.trim().length > 0))) {
                    current.guarded = true;
                }
            },

            ':function:exit'() {
                const { node, flag, guarded } = functions.pop();
                if (!flag) {
                    return;
                }
                if (!registeredFlags.has(flag)) {
                    context.report({ node, messageId: 'unknownFlag', data: { flag } });
                } else if (!guarded) {
                    context.report({ node, messageId: 'missingGuard', data: { flag } });
                }
            },
        };
    },
};
