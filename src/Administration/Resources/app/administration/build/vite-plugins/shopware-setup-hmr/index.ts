/**
 * @sw-package framework
 */
import type { Plugin } from 'vite';
import type { Node } from '@babel/types';
import { parse } from '@babel/parser';
import MagicString from 'magic-string';
import { createHash } from 'node:crypto';

type Metadata = { componentName: string; mode: 'base' | 'override'; version: string };

function visit(node: unknown, callback: (node: Node) => void): void {
    if (Array.isArray(node)) {
        node.forEach((child) => visit(child, callback));
    } else if (node && typeof node === 'object' && 'type' in node) {
        const astNode = node as Node;
        callback(astNode);
        if (
            [
                'FunctionDeclaration',
                'FunctionExpression',
                'ArrowFunctionExpression',
                'ObjectMethod',
                'ClassMethod',
                'ClassPrivateMethod',
            ].includes(astNode.type)
        )
            return;
        Object.values(node).forEach((child) => visit(child, callback));
    }
}

/**
 * The virtual SFC is watched under its original filename, so Vue's descriptor cache cannot reliably
 * classify script updates. Use the source loader's version at the self-accept boundary instead.
 * @private
 */
export default function ShopwareSetupHmrPlugin(): Plugin {
    return {
        name: 'shopware-vite-plugin-shopware-setup-hmr',
        apply: 'serve',
        enforce: 'post',
        transform(code, id) {
            if (!id.includes('.shopware-setup.vue') || !code.includes('import.meta.hot.accept')) return null;
            const output = new MagicString(code);
            const metadata = this.getModuleInfo(id)?.meta.shopwareSetupHmr as Metadata | undefined;
            if (metadata) {
                const ast = parse(code, { sourceType: 'module' });
                const declaration = ast.program.body.find((node) => node.type === 'ExportDefaultDeclaration');
                if (declaration?.type !== 'ExportDefaultDeclaration') return null;
                const expression = declaration.declaration;
                if (
                    declaration.start == null ||
                    declaration.end == null ||
                    expression.start == null ||
                    expression.end == null
                )
                    return null;
                output.overwrite(
                    declaration.start,
                    declaration.end,
                    `
const __swSetupHmrComponent = ${code.slice(expression.start, expression.end)};
__swSetupHmrComponent.__shopwareSetupHmr = {
    ...${JSON.stringify(metadata)},
    data: import.meta.hot.data,
    invalidate: () => import.meta.hot.invalidate('Shopware component script could not be replaced safely'),
};
export default __swSetupHmrComponent;
`,
                );
                const hmrId = `shopware-setup:${createHash('sha256').update(id.replaceAll('\\', '/').split('?')[0]).digest('hex').slice(0, 16)}`;
                const acceptCalls: Array<Extract<Node, { type: 'CallExpression' }>> = [];
                visit(ast, (node) => {
                    if (
                        node.type === 'AssignmentExpression' &&
                        node.left.type === 'MemberExpression' &&
                        node.left.property.type === 'Identifier' &&
                        node.left.property.name === '__hmrId' &&
                        node.right.start != null &&
                        node.right.end != null
                    ) {
                        output.overwrite(node.right.start, node.right.end, JSON.stringify(hmrId));
                    }
                    if (node.type !== 'CallExpression' || node.start == null || node.end == null) return;
                    if (code.slice(node.callee.start ?? 0, node.callee.end ?? 0) !== 'import.meta.hot.accept') return;
                    if (
                        node.arguments.length === 1 &&
                        ['ArrowFunctionExpression', 'FunctionExpression'].includes(node.arguments[0].type)
                    )
                        acceptCalls.push(node);
                });
                const accept = acceptCalls.at(-1);
                if (accept?.start != null && accept.end != null) {
                    output.overwrite(
                        accept.start,
                        accept.end,
                        `import.meta.hot.accept((mod) => {
    const component = mod?.default;
    const runtime = globalThis.__VUE_HMR_RUNTIME__;
    if (!component?.__hmrId) return;
    if (!runtime || import.meta.hot.data.reloadPage) {
        import.meta.hot.invalidate('Shopware component requires a page reload');
        return;
    }
    import.meta.hot.data.updateDefinitions?.(component);
    if (component.__shopwareSetupHmr?.version !== ${JSON.stringify(metadata.version)}) {
        runtime.reload(component.__hmrId, component);
        import.meta.hot.data.refreshParent?.();
    }
    if (component.render) runtime.rerender(component.__hmrId, component.render);
})`,
                    );
                }
            } else {
                output.append(`
if (import.meta.hot) {
    import.meta.hot.accept((mod) => {
        const component = mod?.default;
        const runtime = globalThis.__VUE_HMR_RUNTIME__;
        if (component?.__hmrId && component.render && runtime) {
            runtime.rerender(component.__hmrId, component.render);
        }
    });
}
`);
            }
            return { code: output.toString(), map: output.generateMap({ hires: true, source: id, includeContent: true }) };
        },
    };
}
