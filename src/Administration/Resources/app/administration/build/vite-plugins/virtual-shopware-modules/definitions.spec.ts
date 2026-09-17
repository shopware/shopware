/**
 * @sw-package framework
 *
 * Guards the place the `shopware:*` contract is written down twice: the code strings the Vite plugin
 * emits, and the functions the Jest shims call.
 */

import path from 'node:path';
import {
    MODULE_FAMILIES,
    allSpecifiers,
    defaultExpression,
    exportNames,
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
                    members.length > 0
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
            expect(parseSpecifier('shopware:mixins')).toEqual({ family: 'shopware:mixins' });
            expect(parseSpecifier('shopware:stores')).toEqual({ family: 'shopware:stores' });
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
        ])('serves %s, because it publishes root exports', (family) => {
            expect(registry[family].exports.length).toBeGreaterThan(0);
            expect(exportNames(registry, parseSpecifier(family)!)).toEqual(registry[family].exports);
        });

        it.each([
            'shopware:mixins',
            'shopware:stores',
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
            const emitted = [
                ...members.map((member) => unwrap(evaluate(memberExpression(parsed!, member), probe))),
                unwrap(evaluate(defaultExpression(parsed!) as string, probe)),
            ];
            const resolved = [
                ...members.map((member) => unwrap(resolveVirtualExport(registry, specifier, member, probe))),
                unwrap(resolveVirtualExport(registry, specifier, 'default', probe)),
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

        it('publishes no named exports for a mixin or a store, which must not be destructured', () => {
            const registryEntries = [
                ...Object.values(registry['shopware:mixins'].subpaths),
                ...Object.values(registry['shopware:stores'].subpaths),
            ];

            expect(registryEntries.every((members) => members.length === 0)).toBe(true);
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
