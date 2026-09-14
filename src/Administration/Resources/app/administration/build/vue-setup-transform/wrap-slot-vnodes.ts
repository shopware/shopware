/** @sw-package framework */
import { parse } from '@babel/parser';
import type { Node } from '@babel/types';
import MagicString from 'magic-string';

const VNODE_HELPERS = new Set([
    'createVNode',
    'createBlock',
    'createElementVNode',
    'createElementBlock',
]);

function containsSlotMetadata(node: unknown): boolean {
    if (!node || typeof node !== 'object') return false;
    const value = node as Record<string, unknown>;
    if (value.type === 'ObjectProperty') {
        const key = value.key as { name?: string; value?: string };
        if (key.name === '__swSlotBlocks' || key.value === '__swSlotBlocks') return true;
    }
    if (value.type === 'ArrowFunctionExpression' || value.type === 'FunctionExpression') return false;
    return Object.entries(value).some(
        ([
            key,
            child,
        ]) => key !== 'loc' && (Array.isArray(child) ? child.some(containsSlotMetadata) : containsSlotMetadata(child)),
    );
}

/**
 * Add the slot merge at Vue's VNode construction boundary. Vue still compiles all baseline slot syntax;
 * the helper receives the original receiver VNode before mounting, so component identity and refs survive.
 * @private
 */
export function wrapLegacySlotVNodes(code: string, filename: string) {
    if (!code.includes('__swSlotBlocks')) return null;
    const ast = parse(code, { sourceType: 'unambiguous', plugins: ['typescript'] });
    const helpers = new Set<string>();
    ast.program.body.forEach((node) => {
        if (node.type !== 'ImportDeclaration' || node.source.value !== 'vue') return;
        node.specifiers.forEach((specifier) => {
            if (
                specifier.type === 'ImportSpecifier' &&
                specifier.imported.type === 'Identifier' &&
                VNODE_HELPERS.has(specifier.imported.name)
            ) {
                helpers.add(specifier.local.name);
            }
        });
    });
    const source = new MagicString(code);
    function visit(node: Node): void {
        if (node.type === 'CallExpression' && containsSlotMetadata(node.arguments[1])) {
            const callee = node.callee.type === 'SequenceExpression' ? node.callee.expressions.at(-1) : node.callee;
            const isVNode =
                callee?.type === 'Identifier'
                    ? helpers.has(callee.name)
                    : callee?.type === 'MemberExpression' &&
                      callee.property.type === 'Identifier' &&
                      VNODE_HELPERS.has(callee.property.name);
            if (isVNode) {
                source.prependLeft(node.start!, 'Shopware.Component.applyLegacySlotBlocks(');
                source.appendRight(node.end!, ')');
            }
        }
        Object.entries(node).forEach(
            ([
                key,
                value,
            ]) => {
                if (key === 'loc') return;
                if (Array.isArray(value))
                    value.forEach((child: unknown) => {
                        if (child && typeof child === 'object' && 'type' in child) visit(child as Node);
                    });
                else if (value && typeof value === 'object' && 'type' in value) visit(value as Node);
            },
        );
    }
    visit(ast);
    return { code: source.toString(), map: source.generateMap({ hires: true, source: filename, includeContent: true }) };
}
