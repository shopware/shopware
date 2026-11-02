/**
 * @sw-package framework
 *
 * Jest counterpart of the Vite-generated `shopware:*` modules. Named getters follow tests that replace
 * `window.Shopware`, and stores resolve per call. Default-only values retain their original identity.
 * The CommonJS object has no `__esModule` marker so compiled named imports read its properties, except for a
 * mini-module, whose default export is its own `default` member.
 */

import { getShopwareGlobal, path } from '../dependencies';
import {
    allSpecifiers,
    exportNames,
    isEsModuleSubpath,
    parseSpecifier,
    resolveVirtualExport,
    type ModuleRegistry,
    type VirtualModuleGlobal,
} from './definitions';
import { readRegistry } from './registry';

let registry: ModuleRegistry | undefined;

/** The checked-in registry, read once on first use. */
function getRegistry(): ModuleRegistry {
    registry ??= readRegistry(path.resolve(__dirname, '../../..'));

    return registry;
}

function shopware(): VirtualModuleGlobal {
    return getShopwareGlobal() as VirtualModuleGlobal;
}

/** Reads one member, or `undefined` when a partial Shopware mock does not provide it. */
function read(specifier: string, member: string): unknown {
    try {
        return resolveVirtualExport(getRegistry(), specifier, member, shopware());
    } catch {
        return undefined;
    }
}

/** @private The value one `shopware:*` specifier publishes, read off the global object the test env set up. */
export function resolveShopwareModule(specifier: string): unknown {
    const parsed = parseSpecifier(specifier);
    const members = parsed ? exportNames(getRegistry(), parsed) : undefined;

    if (!parsed || !members) {
        throw new Error(`"${specifier}" is not a Shopware virtual module.`);
    }

    // A mini-module has a `default` member of its own, and the marker makes a default import read it.
    if (members.length > 0 || isEsModuleSubpath(parsed)) {
        const stub = isEsModuleSubpath(parsed) ? { __esModule: true } : {};

        members.forEach((member) =>
            Object.defineProperty(stub, member, {
                get: () => read(specifier, member),
                // Mixed default and named imports copy enumerable CommonJS properties.
                enumerable: true,
            }),
        );

        return stub;
    }

    if (parsed.family === 'shopware:stores') {
        return (...args: unknown[]) =>
            (resolveVirtualExport(getRegistry(), specifier, 'default', shopware()) as (...storeArgs: unknown[]) => unknown)(
                ...args,
            );
    }

    return resolveVirtualExport(getRegistry(), specifier, 'default', shopware());
}

/**
 * @private
 *
 * Makes every `shopware:*` specifier importable from a spec. Vite's plugin generates these modules for
 * the build; Jest does not run Vite, so each one is registered as a mock instead.
 *
 * `virtual: true` is what lets them exist without a file: Jest keys a non-relative virtual mock by the
 * bare specifier, so one registration serves every importer wherever it sits in the tree. It also
 * survives `jest.resetModules()` and `jest.isolateModules()`, because the mock registry is separate from
 * the module registry those clear.
 *
 * The factory is lazy, so a spec that imports nothing virtual never resolves a module.
 *
 * @example
 * registerShopwareModuleMocks(jest);
 */
export function registerShopwareModuleMocks(jestObject: Pick<typeof jest, 'mock'>): void {
    allSpecifiers(getRegistry()).forEach((specifier) =>
        jestObject.mock(specifier, () => resolveShopwareModule(specifier), { virtual: true }),
    );
}
