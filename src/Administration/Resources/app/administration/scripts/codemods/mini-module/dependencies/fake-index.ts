/**
 * @sw-package framework
 * @private
 */

import type { AdminIndex, FileRewrite, GlobalSource, OutsideEdit, UseRewrite } from './model';
import { renderDependencies } from './render-dependencies';
import { DependencyRegistry, routeFile } from './route-file';

const GLOBALS: Record<string, GlobalSource> = {
    'Data.Criteria': {
        kind: 'import',
        module: 'src/core/data/criteria.data',
        imported: 'default',
        name: 'Criteria',
        consumed: 2,
        fromPackage: true,
    },
    'Utils.format.fileSize': { kind: 'function', name: 'fileSize', consumed: 3 },
    'Utils.debounce': {
        kind: 'import',
        module: 'lodash-es/debounce',
        imported: 'default',
        name: 'debounce',
        consumed: 2,
        fromPackage: true,
    },
    Utils: { kind: 'import', module: 'src/core/service/util.service', imported: 'default', name: 'Utils', consumed: 1 },
};

/**
 * A small Administration for the specs of the rewrites: a few services, mixins, stores, filters, helpers
 * and paths of the `Shopware` global, shaped like the real ones.
 */
export const fakeIndex: AdminIndex = {
    isService: (name) =>
        [
            'acl',
            'repositoryFactory',
            'feature',
            'shopwareServicesService',
        ].includes(name),
    hasServiceName: (name) => name === 'repositoryFactory',
    typing: (container, name) => (container === 'ServiceContainer' && name === 'filterService' ? 'any' : 'typed'),
    findMixin: (name) =>
        ({ notification: 'src/app/mixin/notification.mixin', placeholder: 'src/app/mixin/placeholder.mixin' })[name] ?? null,
    isModuleLocalMixin: (name) => name === 'sw-settings-list',
    findStore: (id) =>
        ({
            swProductDetail: { module: '../../page/sw-product-detail/store', file: '/admin/store.ts', exportName: null },
            notification: {
                module: 'src/app/store/notification.store',
                file: '/admin/n.ts',
                exportName: 'notificationStoreId',
            },
        })[id] ?? null,
    findFilter: (name) =>
        name === 'date' ? { module: 'src/app/filter/date.filter', file: '/admin/date.filter.ts', exportName: null } : null,
    findComponentHelper: (name) =>
        ({
            mapPropertyErrors: { module: 'src/app/service/map-errors.service', imported: 'mapPropertyErrors' },
            mapState: { module: 'pinia', imported: 'mapState' },
        })[name] ?? null,
    resolveGlobal: (path) => {
        const key = Object.keys(GLOBALS).find((candidate) => path.join('.').startsWith(candidate));
        return key ? GLOBALS[key] : null;
    },
};

/**
 * Routes a component file of the module `/admin/src/module/sw-x/page/sw-x-page` with the given rewrites,
 * and returns the new source, the exports of dependencies.ts without its header, and the outside edits.
 *
 * @example
 * const { code, dependencies } = routeForSpec(source, { use: [rewriteMixin] });
 */
export function routeForSpec(
    code: string,
    rewrites: { use?: UseRewrite[]; file?: FileRewrite[] },
    fileName = '/admin/src/module/sw-x/page/sw-x-page/src/sw-x-page.js',
): { code: string; dependencies: string; outsideEdits: OutsideEdit[] } {
    const registry = new DependencyRegistry();
    const routed = routeFile(code, {
        fileName,
        moduleRoot: '/admin/src/module/sw-x/page/sw-x-page',
        index: fakeIndex,
        registry,
        useRewrites: rewrites.use ?? [],
        fileRewrites: rewrites.file ?? [],
    });
    const dependencies = renderDependencies('framework', 'sw-x-page', registry.all).replace(/^\/\*\*[\s\S]*?\*\/\n/, '');

    return { code: routed.code, dependencies, outsideEdits: routed.outsideEdits };
}
