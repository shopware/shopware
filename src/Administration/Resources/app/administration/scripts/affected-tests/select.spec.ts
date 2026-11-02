import { selectAffectedSpecs, toAdministrationPath, type Repository } from './select';

const MODULE = 'src/module/sw-shop/component/sw-price';

/**
 * A small repository: one mini-module, a service, a component with an extension, and the specs around them.
 * The sources hold no import statements, because Jest would read them as imports of this spec.
 */
const FILES: Record<string, string> = {
    [`${MODULE}/index.ts`]: "export default definePublicComponent({ name: 'sw-price', load: loadSwPrice });",
    [`${MODULE}/register.ts`]: 'Shopware.Component.register(SwPrice);',
    [`${MODULE}/global.d.ts`]: 'interface PublicComponents {}',
    [`${MODULE}/dependencies.ts`]: 'export const getApiContext = () => Shopware.Context.api;',
    [`${MODULE}/src/sw-price.js`]: 'export default {};',
    [`${MODULE}/tests/requirements.spec.ts`]: 'mount(SwPrice);',
    [`${MODULE}/tests/pinned.spec.ts`]: 'mount(SwPrice);',
    'src/module/sw-shop/page/sw-shop-detail/sw-shop-detail.spec.js':
        "stubs: { 'sw-price': await wrapTestComponent('sw-price') }",
    'src/module/sw-shop/index.js': 'Shopware.Module.register(shop);',
    'src/app/main.spec.js': 'expect(main).toBeDefined();',
    'src/app/service/price.service.ts': 'export default class PriceService {}',
    'src/app/service/price.service.spec.ts': 'new PriceService();',
    'src/app/init/price.init.ts': "Shopware.Service().register('priceService', () => new PriceService());",
    'src/app/init/dynamic.init.ts': 'Shopware.Service().register(serviceName, factory);',
    'src/app/component/sw-card/index.js': "Shopware.Component.register('sw-card', loadSwCard);",
    'src/app/component/sw-card/sw-card.html.twig': '<div class="sw-card"></div>',
    'src/app/component/sw-card/sw-card.scss': '.sw-card {}',
    'src/app/component/sw-card/sw-card.spec.js': "await wrapTestComponent('sw-card');",
    'src/app/component/sw-card-wide/sw-card-wide.spec.js': "await wrapTestComponent('sw-card-wide');",
    'src/app/component/sw-card/sw-card-label.spec.js': "await wrapTestComponent('sw-card-label');",
    'src/app/payment.spec.js': "Shopware.Service('priceService');",
    'src/core/data/criteria.data.ts': 'export default class Criteria {}',
    'src/core/data/criteria.data.spec.ts': 'new Criteria();',
    'src/module/sw-shop/default-config.ts': 'export default {};',
    'src/module/sw-shop/snippet/en.json': '{}',
    'test/_setup/prepare_environment.js': '',
};

/** Who imports whom, as Jest's `--findRelatedTests` would answer: file → specs and setup files reaching it. */
const IMPORTERS: Record<string, string[]> = {
    [`${MODULE}/index.ts`]: [
        `${MODULE}/tests/requirements.spec.ts`,
        `${MODULE}/tests/pinned.spec.ts`,
        'src/app/main.spec.js',
    ],
    [`${MODULE}/global.d.ts`]: [],
    [`${MODULE}/register.ts`]: ['src/app/main.spec.js'],
    'src/app/service/price.service.ts': ['src/app/service/price.service.spec.ts'],
    'src/app/init/price.init.ts': [],
    'src/core/data/criteria.data.ts': ['src/core/data/criteria.data.spec.ts', 'test/_setup/prepare_environment.js'],
};

const COMPONENTS = [
    { name: 'sw-card', directory: 'src/app/component/sw-card' },
    { name: 'sw-card-wide', directory: 'src/app/component/sw-card-wide', extends: 'sw-card' },
];

const repository: Repository = {
    miniModuleOf: (file) => (file.startsWith(`${MODULE}/`) ? MODULE : null),
    specsIn: (directory) => Object.keys(FILES).filter((file) => file.startsWith(`${directory}/`) && file.includes('.spec.')),
    read: (file) => FILES[file] ?? null,
    importersOf: (file) => {
        const importers = IMPORTERS[file] ?? [];

        return Promise.resolve({
            specs: importers.filter((importer) => importer.includes('.spec.')),
            setupFiles: importers.filter((importer) => !importer.includes('.spec.')),
        });
    },
    componentsOwning: (file) =>
        COMPONENTS.filter(({ directory }) => file.startsWith(`${directory}/`)).map(({ name }) => name),
    componentsExtending: (name) =>
        COMPONENTS.filter((component) => component.extends === name).map((component) => component.name),
    specsMentioning: (name) =>
        Object.keys(FILES).filter(
            (file) => file.includes('.spec.') && new RegExp(`(?<![\\w$-])${name}(?![\\w$-])`).test(FILES[file]),
        ),
};

/** The selection as plain data: spec → sorted reasons. */
async function select(...changedFiles: string[]) {
    const selection = await selectAffectedSpecs(changedFiles, repository);

    return {
        specs: Object.fromEntries([...selection.specs].map(([spec, reasons]) => [spec, [...reasons].sort()])),
        uncertain: selection.uncertain,
        skipped: selection.skipped,
    };
}

describe('selectAffectedSpecs', () => {
    describe('inside a mini-module', () => {
        it('runs only the module specs when its src/ changes', async () => {
            // ACT
            const selection = await select(`${MODULE}/src/sw-price.js`);

            // ASSERT
            expect(selection).toEqual({
                specs: {
                    [`${MODULE}/tests/requirements.spec.ts`]: [`mini-module ${MODULE} changed in src/`],
                    [`${MODULE}/tests/pinned.spec.ts`]: [`mini-module ${MODULE} changed in src/`],
                },
                uncertain: [],
                skipped: [],
            });
        });

        it('runs only the module specs when dependencies.ts or a test changes', async () => {
            // ACT
            const selection = await select(`${MODULE}/dependencies.ts`, `${MODULE}/tests/pinned.spec.ts`);

            // ASSERT
            expect(Object.keys(selection.specs).sort()).toEqual([
                `${MODULE}/tests/pinned.spec.ts`,
                `${MODULE}/tests/requirements.spec.ts`,
            ]);
            expect(selection.specs[`${MODULE}/tests/pinned.spec.ts`]).toEqual([
                `mini-module ${MODULE} changed in dependencies.ts`,
                `mini-module ${MODULE} changed in tests/`,
                'the spec itself changed',
            ]);
        });

        it('adds the importers of index.ts when the public API changes', async () => {
            // ACT
            const selection = await select(`${MODULE}/index.ts`);

            // ASSERT
            expect(selection.specs['src/app/main.spec.js']).toEqual([`imports ${MODULE}/index.ts`]);
        });

        it('treats a change of global.d.ts like a change of index.ts', async () => {
            // ACT
            const selection = await select(`${MODULE}/global.d.ts`);

            // ASSERT
            expect(selection.specs['src/app/main.spec.js']).toEqual([`imports ${MODULE}/index.ts`]);
        });

        it('adds the specs that mount the component by name when register.ts changes', async () => {
            // ACT
            const selection = await select(`${MODULE}/register.ts`);

            // ASSERT
            expect(selection.specs['src/module/sw-shop/page/sw-shop-detail/sw-shop-detail.spec.js']).toEqual([
                'mentions component name sw-price',
            ]);
            expect(selection.specs['src/app/main.spec.js']).toEqual([`imports ${MODULE}/register.ts`]);
        });
    });

    describe('outside mini-modules', () => {
        it('follows the import graph', async () => {
            // ACT
            const selection = await select('src/app/service/price.service.ts');

            // ASSERT
            expect(selection).toEqual({
                specs: { 'src/app/service/price.service.spec.ts': ['imports src/app/service/price.service.ts'] },
                uncertain: [],
                skipped: [],
            });
        });

        it('adds the specs that mention a service name the file registers', async () => {
            // ACT
            const selection = await select('src/app/init/price.init.ts');

            // ASSERT
            expect(selection.specs).toEqual({ 'src/app/payment.spec.js': ['mentions service name priceService'] });
        });

        it('adds the specs of the component a template belongs to, and of components extending it', async () => {
            // ACT
            const selection = await select('src/app/component/sw-card/sw-card.html.twig');

            // ASSERT
            expect(selection.specs).toEqual({
                'src/app/component/sw-card/sw-card.spec.js': ['mentions component name sw-card'],
                'src/app/component/sw-card-wide/sw-card-wide.spec.js': [
                    'mentions component name sw-card-wide (extends sw-card)',
                ],
            });
        });

        it('warns about a registration whose name exists only at runtime', async () => {
            // ACT
            const selection = await select('src/app/init/dynamic.init.ts');

            // ASSERT
            expect(selection.uncertain).toEqual([
                {
                    file: 'src/app/init/dynamic.init.ts',
                    reason: 'registers a name that only exists at runtime: Service().register(serviceName',
                },
            ]);
        });

        it('warns about a file the Jest setup loads, but still selects its importers', async () => {
            // ACT
            const selection = await select('src/core/data/criteria.data.ts');

            // ASSERT
            expect(Object.keys(selection.specs)).toEqual(['src/core/data/criteria.data.spec.ts']);
            expect(selection.uncertain).toEqual([
                {
                    file: 'src/core/data/criteria.data.ts',
                    reason: expect.stringContaining('loaded by the Jest setup (test/_setup/prepare_environment.js)'),
                },
            ]);
        });

        it('warns about a file that no spec reaches and that registers no name', async () => {
            // ACT
            const selection = await select('src/module/sw-shop/default-config.ts');

            // ASSERT
            expect(selection.specs).toEqual({});
            expect(selection.uncertain[0].reason).toContain('no spec imports it and it registers no name');
        });

        it('skips a snippet file that no spec imports', async () => {
            // ACT
            const selection = await select('src/module/sw-shop/snippet/en.json');

            // ASSERT
            expect(selection).toEqual({
                specs: {},
                uncertain: [],
                skipped: [
                    {
                        file: 'src/module/sw-shop/snippet/en.json',
                        reason: 'specs show snippet keys and do not load snippet files',
                    },
                ],
            });
        });

        it('warns about a deleted file', async () => {
            // ACT
            const selection = await select('src/app/service/removed.service.ts');

            // ASSERT
            expect(selection.uncertain[0].reason).toContain('is deleted');
        });

        it('runs a changed spec by itself', async () => {
            // ACT
            const selection = await select('src/app/component/sw-card/sw-card.spec.js');

            // ASSERT
            expect(selection).toEqual({
                specs: { 'src/app/component/sw-card/sw-card.spec.js': ['the spec itself changed'] },
                uncertain: [],
                skipped: [],
            });
        });
    });

    it('warns about the test setup, which every spec runs on', async () => {
        // ACT
        const selection = await select('test/_setup/prepare_environment.js');

        // ASSERT
        expect(selection.uncertain).toEqual([
            { file: 'test/_setup/prepare_environment.js', reason: 'changes the test setup that every spec runs on' },
        ]);
    });

    it('skips styles, which Jest replaces by a mock', async () => {
        // ACT
        const selection = await select('src/app/component/sw-card/sw-card.scss');

        // ASSERT
        expect(selection).toEqual({
            specs: {},
            uncertain: [],
            skipped: [{ file: 'src/app/component/sw-card/sw-card.scss', reason: 'styles are mocked in Jest' }],
        });
    });
});

describe('toAdministrationPath', () => {
    it('makes paths from the repository root relative to the Administration', () => {
        // ACT
        const paths = [
            'src/Administration/Resources/app/administration/src/app/main.ts',
            'src/Storefront/Resources/app/administration/src/main.js',
            'src/app/main.ts',
        ].map(toAdministrationPath);

        // ASSERT
        expect(paths).toEqual([
            'src/app/main.ts',
            '../../../../Storefront/Resources/app/administration/src/main.js',
            'src/app/main.ts',
        ]);
    });
});
