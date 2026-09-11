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
