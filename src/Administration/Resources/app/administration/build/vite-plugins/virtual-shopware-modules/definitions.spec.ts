/**
 * @sw-package framework
 *
 * Guards the two places the `shopware:*` contract is written down twice: the code strings the Vite plugin
 * emits versus the functions the Jest shims call, and the export names versus the source keys behind them.
 */

import fs from 'node:fs';
import path from 'node:path';
import { globSync } from 'glob';
import {
    VIRTUAL_MODULES,
    VIRTUAL_MODULE_SPECIFIERS,
    isVirtualModule,
    mixinExportName,
    resolveVirtualExport,
    storeExportName,
    type VirtualModuleGlobal,
} from './definitions';
import { SOURCE_KEY_SPECIFIERS, readSourceKeys, sourceKeyFile } from './source-keys';

const administrationRoot = path.resolve(__dirname, '../../..');

/**
 * A global object whose branches report which key was read, so a generated expression and its runtime
 * counterpart can be compared without the real Administration.
 *
 * It knows only `knownKeys` and rejects everything else, the way the real registries do - which is what
 * makes the candidate order in `sourceKeyCandidates` observable here.
 */
function createProbeGlobal(knownKeys: string[] = []): VirtualModuleGlobal {
    const known = new Set(knownKeys);

    const branch = (name: string) =>
        new Proxy({} as Record<string, unknown>, {
            get: (_target, property) => `${name}:${String(property)}`,
            has: (_target, property) => known.has(String(property)),
        });

    const registry = (name: string) => (key: string) => {
        if (!known.has(key)) {
            throw new Error(`The ${name} "${key}" is not registered.`);
        }

        return `${name}:${key}`;
    };

    return {
        Utils: branch('Utils'),
        Data: branch('Data'),
        Mixin: { getByName: registry('Mixin') },
        Store: { get: registry('Store') },
    };
}

/** Evaluates a generated initialiser the way the generated module would. */
function evaluateExpression(expression: string, shopware: VirtualModuleGlobal): unknown {
    // eslint-disable-next-line @typescript-eslint/no-implied-eval
    const initialiser = new Function('shopware', `return (${expression});`) as (global: VirtualModuleGlobal) => unknown;

    return initialiser(shopware);
}

/**
 * Calls a resolved export when it is a lazy one, so eager and lazy modules compare the same way.
 *
 * Against the probe global every value is a marker string, so the only functions here are the store
 * lookups the generated module defers.
 */
function unwrap(value: unknown): unknown {
    return typeof value === 'function' ? (value as () => unknown)() : value;
}

describe('build/vite-plugins/virtual-shopware-modules/definitions', () => {
    it('declares a source of export names for every module', () => {
        expect(SOURCE_KEY_SPECIFIERS.sort()).toEqual([...VIRTUAL_MODULE_SPECIFIERS].sort());
    });

    it('recognises only its own specifiers', () => {
        expect(isVirtualModule('shopware:utils')).toBe(true);
        expect(isVirtualModule('shopware:nope')).toBe(false);
        expect(isVirtualModule('src/core/service/util.service')).toBe(false);
    });

    describe.each(VIRTUAL_MODULE_SPECIFIERS)('%s', (specifier) => {
        const definition = VIRTUAL_MODULES[specifier];
        const sourceKeys = readSourceKeys(specifier, administrationRoot);

        it('reads its export names from an existing Administration source', () => {
            expect(sourceKeyFile(specifier, administrationRoot)).toContain(administrationRoot);
            expect(sourceKeys.length).toBeGreaterThan(0);
        });

        it('publishes every key under a unique, valid identifier', () => {
            const exportNames = sourceKeys.map((key) => definition.exportName(key));

            expect(exportNames.filter((name) => !/^[A-Za-z_$][\w$]*$/.test(name))).toEqual([]);
            expect(new Set(exportNames).size).toBe(exportNames.length);
        });

        it('emits the same value it resolves at runtime', () => {
            const probe = createProbeGlobal(sourceKeys);
            const emitted = sourceKeys.map((key) => unwrap(evaluateExpression(definition.expression(key), probe)));
            const resolved = sourceKeys.map((key) => unwrap(definition.read(probe, key)));

            expect(emitted).toEqual(resolved);
        });

        it('resolves every export name back to the key it was made from', () => {
            const probe = createProbeGlobal(sourceKeys);
            const viaName = sourceKeys.map((key) =>
                unwrap(resolveVirtualExport(specifier, definition.exportName(key), probe)),
            );
            const viaKey = sourceKeys.map((key) => unwrap(definition.read(probe, key)));

            expect(viaName).toEqual(viaKey);
        });
    });

    describe('shopware:mixins', () => {
        /**
         * Vite serves the generated module unbundled in dev, so importing it resolves every declared
         * mixin at once and a single unregistered one takes the whole module down. `MixinContainer` was
         * type-only before, so this couples it to the registry: what is declared has to be registered.
         */
        it('publishes only mixins that a mixin file registers', () => {
            const srcDir = path.join(administrationRoot, 'src');
            const moduleDir = path.join(srcDir, 'module');
            const mixinDirs = [
                path.join(srcDir, 'app', 'mixin'),
                ...fs
                    .readdirSync(moduleDir)
                    .map((module) => path.join(moduleDir, module, 'mixin'))
                    .filter((dir) => fs.existsSync(dir)),
            ];

            const registrations = mixinDirs
                .flatMap((dir) =>
                    fs
                        .readdirSync(dir)
                        .filter((file) => /\.[jt]s$/.test(file) && !file.includes('.spec.'))
                        .map((file) => fs.readFileSync(path.join(dir, file), 'utf8')),
                )
                .join('\n');

            // A broken walk must fail loudly rather than vacuously pass.
            expect(registrations).toContain('Shopware.Mixin.register(');

            const declared = readSourceKeys('shopware:mixins', administrationRoot);

            expect(declared.filter((mixinName) => !registrations.includes(`'${mixinName}'`))).toEqual([]);
        });
    });

    describe('shopware:stores', () => {
        /**
         * `PiniaRootState` is the export list, so a key that no store registers under becomes an export
         * whose `Shopware.Store.get()` throws when called. The interface was type-only before, where a
         * wrong key only meant a wrong type.
         */
        it('publishes only stores that a store file registers', () => {
            const registrations = globSync(
                [
                    'src/**/*.store.{ts,js}',
                    'src/**/store.{ts,js}',
                ],
                {
                    cwd: administrationRoot,
                    absolute: true,
                    ignore: [
                        '**/*.spec.{ts,js}',
                        '**/*.spec/**',
                    ],
                },
            )
                .map((file) => fs.readFileSync(file, 'utf8'))
                .join('\n');

            // A broken walk must fail loudly rather than vacuously pass.
            expect(registrations).toContain('Store.register(');

            const declared = readSourceKeys('shopware:stores', administrationRoot);

            expect(declared.filter((storeId) => !registrations.includes(`'${storeId}'`))).toEqual([]);
        });
    });

    describe('shopware:utils and shopware:data mirror the global object', () => {
        it.each([
            [
                'shopware:utils',
                () => Shopware.Utils,
            ],
            [
                'shopware:data',
                () => Shopware.Data,
            ],
        ])('%s exports exactly the keys of its branch', (specifier, branch) => {
            expect(readSourceKeys(specifier, administrationRoot).sort()).toEqual(Object.keys(branch()).sort());
        });
    });

    describe('export names', () => {
        it.each([
            [
                'sw-form-field',
                'swFormFieldMixin',
            ],
            [
                'remove-api-error',
                'removeApiErrorMixin',
            ],
            [
                'notification',
                'notificationMixin',
            ],
            [
                'ruleContainer',
                'ruleContainerMixin',
            ],
        ])('turns the mixin "%s" into "%s"', (mixinName, expected) => {
            expect(mixinExportName(mixinName)).toBe(expected);
        });

        it.each([
            [
                'cart',
                'useCartStore',
            ],
            [
                'swOrderDetail',
                'useSwOrderDetailStore',
            ],
        ])('turns the store "%s" into "%s"', (storeId, expected) => {
            expect(storeExportName(storeId)).toBe(expected);
        });
    });

    describe('resolveVirtualExport', () => {
        it('rejects an unknown module', () => {
            expect(() => resolveVirtualExport('shopware:nope', 'anything', createProbeGlobal())).toThrow(
                '"shopware:nope" is not a Shopware virtual module.',
            );
        });

        it('names the module and the candidates it tried when an export does not exist', () => {
            expect(() => resolveVirtualExport('shopware:utils', 'notAUtil', Shopware as never)).toThrow(
                /"shopware:utils" has no export "notAUtil".*Shopware\.Utils.*"notAUtil"/s,
            );
        });
    });
});
