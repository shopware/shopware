/**
 * @sw-package framework
 */
import type { Plugin } from 'vite';
import type { Statement } from '@babel/types';
import { parse } from '@babel/parser';
import MagicString from 'magic-string';
import type { ShopwareSetupHmrMeta } from '../shopware-setup';

const reloadReason = 'Shopware cannot apply this component edit in place';

function parseStatements(code: string): Statement[] {
    try {
        return parse(code, { sourceType: 'module' }).program.body;
    } catch {
        return [];
    }
}

function isSelfAccept(statement: Statement, code: string): boolean {
    if (statement.type !== 'ExpressionStatement' || statement.expression.type !== 'CallExpression') {
        return false;
    }

    const { callee, arguments: args } = statement.expression;

    return (
        code.slice(callee.start ?? 0, callee.end ?? 0) === 'import.meta.hot.accept' &&
        args.length === 1 &&
        (args[0].type === 'ArrowFunctionExpression' || args[0].type === 'FunctionExpression')
    );
}

// Runs in the module that is replaced, so the metadata is the one of the version the page runs.
function acceptHandler({ mode, file, version, structure }: ShopwareSetupHmrMeta): string {
    // The factory keeps its own copy of a base, a copy that is not mounted would keep the old script.
    const unmountedBase = mode === 'base' ? ' || !data.instances?.size' : '';
    const invalidate = `import.meta.hot.invalidate(${JSON.stringify(reloadReason)})`;

    return `import.meta.hot.accept((mod) => {
    const component = mod?.default;
    const data = import.meta.hot.data;
    const runtime = globalThis.__VUE_HMR_RUNTIME__;
    if (!component?.__hmrId) return;
    if (!runtime || data.reloadPage || component.__shopwareSetupHmr?.structure !== ${JSON.stringify(structure)}${unmountedBase}) {
        ${invalidate};
        return;
    }
    // An edit of an imported file keeps the version, but needs the new module to run.
    if (component.__shopwareSetupHmr.version === ${JSON.stringify(version)} && runtime.CHANGED_FILE === ${JSON.stringify(file)}) {
        runtime.rerender(component.__hmrId, component.render);
    } else {
        runtime.reload(component.__hmrId, component);
    }
    __swNextTick().catch(() => ${invalidate});
})`;
}

/**
 * Replaces the hot update of `@vitejs/plugin-vue` for native setup SFCs.
 *
 * Its own handler reloads the module export, which drops what the component factory and the
 * override registry added at boot. This handler rerenders template edits, reloads script edits
 * together with the overridden components, and reloads the page when neither is possible or when
 * rendering the update fails.
 *
 * @private
 */
export default function ShopwareSetupHmrPlugin(): Plugin {
    return {
        name: 'shopware-vite-plugin-shopware-setup-hmr',
        apply: 'serve',
        enforce: 'post',

        transform(code, id) {
            const metadata = id.endsWith('.shopware-setup.vue')
                ? (this.getModuleInfo(id)?.meta.shopwareSetupHmr as ShopwareSetupHmrMeta | undefined)
                : undefined;

            if (!metadata) {
                return null;
            }

            const output = new MagicString(code);
            const statements = parseStatements(code);
            const exportDefault = statements.find((statement) => statement.type === 'ExportDefaultDeclaration');
            const component = exportDefault?.type === 'ExportDefaultDeclaration' ? exportDefault.declaration : undefined;
            const accept = statements.filter((statement) => isSelfAccept(statement, code)).at(-1);

            if (component?.start == null || component.end == null || accept?.start == null || accept.end == null) {
                this.warn(`Cannot hot update ${id}, the page reloads on every edit instead.`);
                output.append(
                    `\nimport.meta.hot.accept(() => import.meta.hot.invalidate(${JSON.stringify(reloadReason)}));\n`,
                );

                return { code: output.toString(), map: output.generateMap({ hires: true, source: id }) };
            }

            output.prepend("import { nextTick as __swNextTick } from 'vue';\n");
            output.prependLeft(component.start, 'Object.assign(');
            output.appendRight(
                component.end,
                `, { __shopwareSetupHmr: { ...${JSON.stringify(metadata)}, data: import.meta.hot.data } })`,
            );
            output.overwrite(accept.start, accept.end, `${acceptHandler(metadata)};`);

            return { code: output.toString(), map: output.generateMap({ hires: true, source: id }) };
        },
    };
}
