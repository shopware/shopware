/**
 * @sw-package framework
 */

type WrapTestComponent = (componentName: string, config?: { sync?: boolean }) => Promise<unknown>;

/**
 * Loads `wrapTestComponent` against a stand-in import map, so a stale or missing entry can be
 * reproduced without touching the generated `component-imports.js`.
 */
async function loadWrapTestComponent(
    importMap: Record<string, { p: string; r?: boolean; en?: string }>,
): Promise<WrapTestComponent> {
    jest.resetModules();
    jest.doMock('./component-imports', () => ({
        __esModule: true,
        default: importMap,
    }));

    return (await import('./index')).default as WrapTestComponent;
}

/**
 * Importable paths whose module evaluation is replaced per test. They have to resolve, so that only
 * the factory - not the resolution - is what fails.
 */
const THROWING_MODULE = 'src/app/component/base/sw-button/index';
const STALE_IMPORTER_MODULE = 'src/app/component/base/sw-icon/index';

/**
 * Runs a wrap that is expected to fail and returns the error it threw.
 */
async function captureError(wrap: Promise<unknown>): Promise<Error> {
    return wrap.then(
        () => {
            throw new Error('Expected the component wrap to fail, but it resolved.');
        },
        (caught: Error) => caught,
    );
}

describe('wrapTestComponent', () => {
    afterEach(() => {
        jest.dontMock('./component-imports');
        jest.dontMock(THROWING_MODULE);
        jest.dontMock(STALE_IMPORTER_MODULE);
        jest.resetModules();
    });

    it('names the import map and how to regenerate it when an entry points at a missing file', async () => {
        const wrapTestComponent = await loadWrapTestComponent({
            'sw-moved-component': { p: 'src/app/component/base/sw-moved-component.vue', r: true },
        });

        const error = await captureError(wrapTestComponent('sw-moved-component'));

        expect(error.message).toContain('src/app/component/base/sw-moved-component.vue');
        expect(error.message).toContain('test/_helper_/componentWrapper/component-imports.js');
        expect(error.message).toContain('npm run generate-component-import-resolver-map');
        // The original resolution error stays reachable, so Jest's own message is not lost.
        expect(error.cause).toBeDefined();
    });

    it('names the import map when the component has no entry at all', async () => {
        const wrapTestComponent = await loadWrapTestComponent({});

        const error = await captureError(wrapTestComponent('sw-unmapped-component'));

        expect(error.message).toContain('sw-unmapped-component');
        expect(error.message).toContain('test/_helper_/componentWrapper/component-imports.js');
        expect(error.message).toContain('npm run generate-component-import-resolver-map');
    });

    it('leaves an error thrown while the component module evaluates untouched', async () => {
        const wrapTestComponent = await loadWrapTestComponent({
            'sw-throwing-component': { p: THROWING_MODULE, r: true },
        });
        jest.doMock(THROWING_MODULE, () => {
            throw new Error('Cannot read properties of undefined (reading "criteria")');
        });

        const error = await captureError(wrapTestComponent('sw-throwing-component'));

        expect(error.message).toContain('Cannot read properties of undefined (reading "criteria")');
        expect(error.message).not.toContain('test/_helper_/componentWrapper/component-imports.js');
        expect(error.message).not.toContain('npm run generate-component-import-resolver-map');
        // Rethrown as-is, so it is not merely a relabel that happens to quote the original text.
        expect(error.cause).toBeUndefined();
    });

    it('leaves a stale import inside the component untouched', async () => {
        const wrapTestComponent = await loadWrapTestComponent({
            'sw-stale-importer-component': { p: STALE_IMPORTER_MODULE, r: true },
        });
        // The component itself resolves; one of its own imports does not. That raises the same
        // `moduleNameMapper` error as a stale map entry, but names a different module.
        jest.doMock(STALE_IMPORTER_MODULE, () => {
            jest.requireActual('src/app/component/base/sw-moved-dependency/index');

            return {};
        });

        const error = await captureError(wrapTestComponent('sw-stale-importer-component'));

        expect(error.message).toContain('Could not locate module');
        expect(error.message).toContain('src/app/component/base/sw-moved-dependency/index');
        expect(error.message).not.toContain('npm run generate-component-import-resolver-map');
        expect(error.cause).toBeUndefined();
    });

    it('reports the extended component as part of the chain that requested it', async () => {
        const wrapTestComponent = await loadWrapTestComponent({
            // An importable path, so the run reaches the extended component instead of failing earlier.
            'sw-child-component': {
                p: 'src/app/component/structure/sw-block-override/sw-block-parent/index',
                en: 'sw-missing-parent',
            },
        });

        const error = await captureError(wrapTestComponent('sw-child-component'));

        expect(error.message).toContain('sw-child-component -> sw-missing-parent');
    });
});
