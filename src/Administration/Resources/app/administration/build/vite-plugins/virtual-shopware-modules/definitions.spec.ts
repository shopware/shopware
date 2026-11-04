/**
 * @sw-package framework
 *
 * Guards the place the `shopware:*` contract is written down twice: the code strings the Vite plugin
 * emits, and the functions the Jest shims call.
 */

import path from 'node:path';
import vm from 'node:vm';
import { PUBLIC_KINDS, REGISTRY_KINDS } from 'src/core/mini-module-registry';
import {
    MODULE_FAMILIES,
    allSpecifiers,
    branchGuard,
    defaultExpression,
    exportNames,
    hasDefaultExport,
    isEsModuleSubpath,
    memberExpression,
    parseSpecifier,
    resolveVirtualExport,
    type VirtualModuleGlobal,
} from './definitions';
import { readRegistry } from './index';

const administrationRoot = path.resolve(__dirname, '../../..');
const registry = readRegistry(administrationRoot);

/**
 * A global object shaped like the registry says the real one is, with a marker string at every leaf.
 *
 * Built from the registry rather than a blanket proxy, because a namespace subpath reads two levels deep
 * and both levels have to behave like the real objects for the comparison to mean anything.
 *
 * A marker string stands where the real branch has a function or a class, so the two branch types are
 * asserted rather than satisfied.
 */
function createProbeGlobal(): VirtualModuleGlobal {
    const branchOf = (family: string, property: string): Record<string, unknown> =>
        Object.fromEntries(
            Object.entries(registry[family].subpaths).map(
                ([
                    key,
                    members,
                ]) => [
                    key,
                    members.length > 0 || isEsModuleSubpath({ family, subpath: key })
                        ? Object.fromEntries(
                              members.map((member) => [
                                  member,
                                  `${property}:${key}:${member}`,
                              ]),
                          )
                        : `${property}:${key}`,
                ],
            ),
        );

    return {
        Utils: branchOf('shopware:utils', 'Utils') as unknown as VirtualModuleGlobal['Utils'],
        Data: branchOf('shopware:data', 'Data') as unknown as VirtualModuleGlobal['Data'],
        Composables: branchOf('shopware:composables', 'Composables') as unknown as VirtualModuleGlobal['Composables'],
        Public: Object.fromEntries(
            PUBLIC_KINDS.map((kind) => [
                kind,
                branchOf(`shopware:${kind}`, `Public:${kind}`),
            ]),
        ) as VirtualModuleGlobal['Public'],
        Mixin: { getByName: (key) => `Mixin:${key}` },
        Store: { get: (id) => `Store:${id}` },
    };
}

/** Evaluates a generated initialiser the way the generated module would. */
function evaluate(expression: string, shopware: VirtualModuleGlobal): unknown {
    // eslint-disable-next-line @typescript-eslint/no-implied-eval
    const initialiser = new Function('shopware', `return (${expression});`) as (global: VirtualModuleGlobal) => unknown;

    return initialiser(shopware);
}

/** Calls a lazily resolved export, so eager and lazy modules compare the same way. */
function unwrap(value: unknown): unknown {
    return typeof value === 'function' ? (value as () => unknown)() : value;
}

describe('build/vite-plugins/virtual-shopware-modules/definitions', () => {
    it('has a registry entry for every module family', () => {
        expect(Object.keys(registry).sort()).toEqual([...MODULE_FAMILIES].sort());
    });

    describe('parseSpecifier', () => {
        it('splits a subpath import into its family and subpath', () => {
            expect(parseSpecifier('shopware:utils/debug')).toEqual({ family: 'shopware:utils', subpath: 'debug' });
            expect(parseSpecifier('shopware:mixins/sw-form-field')).toEqual({
                family: 'shopware:mixins',
                subpath: 'sw-form-field',
            });
        });

        it('splits a bare import of any known family', () => {
            expect(parseSpecifier('shopware:utils')).toEqual({ family: 'shopware:utils' });
            expect(parseSpecifier('shopware:data')).toEqual({ family: 'shopware:data' });
            expect(parseSpecifier('shopware:composables')).toEqual({ family: 'shopware:composables' });
            expect(parseSpecifier('shopware:mixins')).toEqual({ family: 'shopware:mixins' });
            expect(parseSpecifier('shopware:stores')).toEqual({ family: 'shopware:stores' });
            expect(parseSpecifier('shopware:components')).toEqual({ family: 'shopware:components' });
            expect(parseSpecifier('shopware:framework')).toEqual({ family: 'shopware:framework' });
        });

        it('leaves every other import alone', () => {
            expect(parseSpecifier('shopware:nope')).toBeUndefined();
            expect(parseSpecifier('shopware:utils/')).toBeUndefined();
            expect(parseSpecifier('constructor/member')).toBeUndefined();
            expect(parseSpecifier('toString')).toBeUndefined();
            expect(parseSpecifier('src/core/service/util.service')).toBeUndefined();
            expect(parseSpecifier('vue')).toBeUndefined();
        });
    });

    describe('the registry decides which root imports resolve', () => {
        it.each([
            'shopware:utils',
            'shopware:data',
            'shopware:composables',
        ])('serves %s, because it publishes root exports', (family) => {
            expect(registry[family].exports.length).toBeGreaterThan(0);
            expect(exportNames(registry, parseSpecifier(family)!)).toEqual(registry[family].exports);
        });

        it.each([
            ...new Set([
                'shopware:mixins',
                'shopware:stores',
                ...PUBLIC_KINDS.map((kind) => `shopware:${kind}`),
            ]),
        ])('refuses %s, because it publishes none', (family) => {
            expect(registry[family].exports).toEqual([]);
            expect(exportNames(registry, parseSpecifier(family)!)).toBeUndefined();
        });

        it('separates a root import that does not resolve from a subpath that is default-only', () => {
            expect(exportNames(registry, parseSpecifier('shopware:mixins')!)).toBeUndefined();
            expect(exportNames(registry, parseSpecifier('shopware:mixins/sw-form-field')!)).toEqual([]);
        });

        it.each([
            'constructor',
            'toString',
            '__proto__',
        ])('refuses the inherited subpath %s', (subpath) => {
            expect(exportNames(registry, parseSpecifier(`shopware:utils/${subpath}`)!)).toBeUndefined();
        });
    });

    describe('the emitted code and the runtime resolver agree', () => {
        it.each(allSpecifiers(registry))('%s', (specifier) => {
            const parsed = parseSpecifier(specifier);
            const probe = createProbeGlobal();

            expect(parsed).toBeDefined();

            const members = exportNames(registry, parsed!) ?? [];
            const withDefault = hasDefaultExport(members, parsed!);
            const emitted = [
                ...members.map((member) => unwrap(evaluate(memberExpression(parsed!, member), probe))),
                ...(withDefault ? [unwrap(evaluate(defaultExpression(parsed!) as string, probe))] : []),
            ];
            const resolved = [
                ...members.map((member) => unwrap(resolveVirtualExport(registry, specifier, member, probe))),
                ...(withDefault ? [unwrap(resolveVirtualExport(registry, specifier, 'default', probe))] : []),
            ];

            expect(emitted).toEqual(resolved);
        });
    });

    describe('the registry matches the global object', () => {
        it.each([
            [
                'shopware:utils',
                () => Shopware.Utils,
            ],
            [
                'shopware:data',
                () => Shopware.Data,
            ],
            [
                'shopware:composables',
                () => Shopware.Composables,
            ],
        ])('%s publishes exactly the keys of its branch', (family, branch) => {
            expect(registry[family].exports.sort()).toEqual(Object.keys(branch()).sort());
            expect(Object.keys(registry[family].subpaths).sort()).toEqual(Object.keys(branch()).sort());
        });

        it('promises no named export a utility namespace does not have', () => {
            const utils = Shopware.Utils as unknown as Record<string, unknown>;

            Object.entries(registry['shopware:utils'].subpaths).forEach(
                ([
                    key,
                    members,
                ]) => {
                    const value = utils[key] as Record<string, unknown>;
                    const missing = members.filter((member) => !(member in value));

                    expect(missing, `shopware:utils/${key} promises members it does not have`).toEqual([]);
                },
            );
        });

        it('publishes exactly the index.ts exports of every mini-module, under its kind, through its register.ts', async () => {
            // Every mini-module as `<kind>/<name>`, so a module published under the wrong kind shows up.
            const modules = PUBLIC_KINDS.flatMap((kind) =>
                Object.entries(registry[`shopware:${kind}`].sources ?? {}).map(
                    ([
                        name,
                        source,
                    ]) => ({ key: `${kind}/${name}`, source, members: registry[`shopware:${kind}`].subpaths[name] }),
                ),
            );
            const indexExports: Record<string, string[]> = {};
            const published: Record<string, object> = {};

            // A fresh registry, so the check sees what the register.ts files publish and nothing else.
            await jest.isolateModulesAsync(async () => {
                const isolated = await import('src/core/mini-module-registry');
                const publish = jest.spyOn(Shopware._private, 'publish').mockImplementation(isolated.publish);

                await Promise.all(
                    modules.map(async ({ key, source }) => {
                        await import(path.join(administrationRoot, source, 'register'));
                        indexExports[key] = Object.keys(
                            (await import(path.join(administrationRoot, source, 'index'))) as object,
                        );
                    }),
                );

                publish.mockRestore();
                PUBLIC_KINDS.forEach((kind) =>
                    Object.entries(isolated.publicModules[kind]).forEach(
                        ([
                            name,
                            exports,
                        ]) => {
                            published[`${kind}/${name}`] = exports;
                        },
                    ),
                );
            });

            expect(Object.keys(published).sort()).toEqual(modules.map(({ key }) => key).sort());

            modules.forEach(({ key }) => {
                expect(Object.keys(published[key]).sort(), `${key} publishes more or less than its index.ts`).toEqual(
                    [...indexExports[key]].sort(),
                );
            });

            // A store or mixin specifier serves the registered entry alone, not the exports of its module.
            modules
                .filter(({ key }) => !(REGISTRY_KINDS as readonly string[]).includes(key.split('/')[0]))
                .forEach(({ key, members }) => {
                    expect(Object.keys(published[key]).sort(), `${key} is stale in shopware-modules.json`).toEqual(
                        [...members].sort(),
                    );
                });
        });

        it('publishes no named exports for a mixin or a store, which must not be destructured', () => {
            const registryEntries = [
                ...Object.values(registry['shopware:mixins'].subpaths),
                ...Object.values(registry['shopware:stores'].subpaths),
            ];

            expect(registryEntries.every((members) => members.length === 0)).toBe(true);
        });
    });

    describe('mini-modules', () => {
        it('serve the default export of an index.ts as the default export of the module', () => {
            const parsed = parseSpecifier('shopware:components/sw-example')!;

            expect(isEsModuleSubpath(parsed)).toBe(true);
            expect(defaultExpression(parsed)).toBe('shopware.Public["components"]["sw-example"]["default"]');
            expect(hasDefaultExport(['default', 'helper'], parsed)).toBe(true);
        });

        it('serve a store or a mixin module by the name it registers under, from the live registry', () => {
            const store = parseSpecifier('shopware:stores/swExample')!;
            const mixin = parseSpecifier('shopware:mixins/sw-example')!;

            expect(isEsModuleSubpath(store)).toBe(false);
            expect(defaultExpression(store)).toBe('() => shopware.Store.get("swExample")');
            expect(isEsModuleSubpath(mixin)).toBe(false);
            expect(defaultExpression(mixin)).toBe('/*@__PURE__*/ shopware.Mixin.getByName("sw-example")');
        });

        it('serve a service module from Shopware.Public, like a helper', () => {
            expect(defaultExpression(parseSpecifier('shopware:services/sw-example')!)).toBe(
                'shopware.Public["services"]["sw-example"]["default"]',
            );
        });

        it('have no default export when their index.ts has none', () => {
            expect(hasDefaultExport(['helper'], parseSpecifier('shopware:helpers/sw-example')!)).toBe(false);
        });
    });

    describe('branchGuard', () => {
        const runGuard = (family: string, shopware: Record<string, unknown>): void => {
            vm.runInNewContext(branchGuard({ family }).join('\n'), { shopware });
        };

        it('names the required and the installed version when an older Administration lacks the branch', () => {
            const shopware = { Utils: {}, Context: { app: { config: { version: '6.7.15.0' } } } };

            expect(() => runGuard('shopware:composables', shopware)).toThrow(
                '"shopware:composables" requires Shopware 6.7.16.0 or later, but the installed version is 6.7.15.0. ' +
                    'Require shopware/administration >=6.7.16.0 in the composer.json of the extension.',
            );
        });

        it('guards the mini-modules, which are newer than the shopware:* modules', () => {
            expect(() => runGuard('shopware:components', { Context: { app: { config: { version: '6.7.16.0' } } } })).toThrow(
                '"shopware:components" requires Shopware 6.7.17.0 or later, but the installed version is 6.7.16.0.',
            );
        });

        it('passes for a mini-module kind when the Administration publishes it', () => {
            expect(() => runGuard('shopware:components', { Public: { components: {} } })).not.toThrow();
        });

        it('passes when the Administration has the branch', () => {
            expect(() => runGuard('shopware:composables', { Composables: {} })).not.toThrow();
        });

        it.each([
            'shopware:utils',
            'shopware:data',
            'shopware:mixins',
            'shopware:stores',
        ])('adds nothing for %s, which every Administration with shopware:* modules has', (family) => {
            expect(branchGuard({ family })).toEqual([]);
        });
    });

    describe('resolveVirtualExport', () => {
        it('rejects a specifier it does not serve', () => {
            expect(() => resolveVirtualExport(registry, 'shopware:mixins', 'anything', createProbeGlobal())).toThrow(
                '"shopware:mixins" is not a Shopware virtual module.',
            );
        });

        it('names the module when a root import has no such export', () => {
            expect(() => resolveVirtualExport(registry, 'shopware:utils', 'notAUtil', Shopware as never)).toThrow(
                '"notAUtil" does not exist on Shopware.Utils.',
            );
        });

        it('names the module when a subpath has no such export', () => {
            expect(() => resolveVirtualExport(registry, 'shopware:utils/debug', 'notAMember', Shopware as never)).toThrow(
                '"shopware:utils/debug" has no export "notAMember".',
            );
        });
    });
});
