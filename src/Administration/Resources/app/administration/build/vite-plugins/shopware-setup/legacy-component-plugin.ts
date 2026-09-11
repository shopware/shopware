/** @sw-package framework */
import { wrapLegacySlotVNodes } from '../../vue-setup-transform/wrap-slot-vnodes';
import type { Plugin } from 'vite';
import { parse } from '@babel/parser';
import MagicString from 'magic-string';

/**
 * Wrap the final Vue component, after Vue has emitted props and render functions.
 * Rewriting the SFC source earlier would let Vue's generated options overwrite legacy definitions.
 * @private
 */
export default function legacyComponentPlugin(): Plugin {
    return {
        name: 'shopware-legacy-sfc-definition',
        enforce: 'post',
        transform(code, id) {
            if (id.includes('/node_modules/')) return null;
            if (!id.endsWith('.vue')) return null;
            const authoredId = id.replace(/\.shopware-setup\.vue$/, '');
            if (authoredId.endsWith('.override.vue')) return null;
            const virtualBase = id.endsWith('.shopware-setup.vue');
            if (!virtualBase && !code.includes('__swExtendable')) return null;
            const ast = parse(code, { sourceType: 'module' });
            const exported = ast.program.body.find((node) => node.type === 'ExportDefaultDeclaration');
            if (!exported || exported.type !== 'ExportDefaultDeclaration') return null;
            const declaration = exported.declaration;
            if (declaration.type === 'FunctionDeclaration' || declaration.type === 'ClassDeclaration') return null;
            const componentName = authoredId
                .replace(/\\/g, '/')
                .split('/')
                .pop()!
                .replace(/\.vue$/, '');
            const name = componentName === 'index' ? id.replace(/\\/g, '/').split('/').at(-2)! : componentName;
            const source = new MagicString(code);
            prepareHotUpdate(source, code, ast.program);
            source.prependLeft(declaration.start!, 'Shopware.Component.createLegacyComponent(');
            source.appendRight(declaration.end!, `, ${JSON.stringify(name)})`);
            return { code: source.toString(), map: source.generateMap({ hires: true, source: id, includeContent: true }) };
        },
    };
}

/** @private */
export function legacySlotBlocksPlugin(): Plugin {
    return { name: 'shopware-legacy-slot-blocks', enforce: 'post', transform: (code, id) => wrapLegacySlotVNodes(code, id) };
}

/** Vue's HMR receiver needs the resolved definition, not the direct-import async wrapper. */
function prepareHotUpdate(source: MagicString, code: string, program: ReturnType<typeof parse>['program']): void {
    for (const statement of program.body) {
        if (statement.type !== 'ExpressionStatement' || statement.expression.type !== 'CallExpression') continue;
        const call = statement.expression;
        if (code.slice(call.callee.start!, call.callee.end!).replace(/\s/g, '') !== 'import.meta.hot.accept') continue;
        const callback = call.arguments[0];
        if (callback?.type !== 'ArrowFunctionExpression' || callback.body.type !== 'BlockStatement') continue;
        const declaration = callback.body.body
            .flatMap((entry) => (entry.type === 'VariableDeclaration' ? entry.declarations : []))
            .find(
                (entry) =>
                    entry.id.type === 'ObjectPattern' &&
                    entry.id.properties.some(
                        (property) =>
                            property.type === 'ObjectProperty' &&
                            property.key.type === 'Identifier' &&
                            property.key.name === 'default',
                    ),
            );
        if (!declaration?.init) continue;
        if (!callback.async) source.prependLeft(callback.start!, 'async ');
        source.prependLeft(declaration.init.start!, 'await Shopware.Component.resolveLegacyHotUpdate(');
        source.appendRight(declaration.init.end!, ')');
    }
}
