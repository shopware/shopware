/**
 * @sw-package framework
 */
import ShopwareSetupHmrPlugin from './index';
import vm from 'node:vm';

describe('build/vite-plugins/shopware-setup-hmr', () => {
    const hook = ShopwareSetupHmrPlugin().transform as unknown as (
        this: unknown,
        code: string,
        id: string,
    ) => { code: string; map: unknown } | null;
    const transform = (code: string, id: string, metadata?: object) =>
        hook.call({ getModuleInfo: () => ({ meta: { shopwareSetupHmr: metadata } }) }, code, id);

    it('adds a render update handler to Shopware setup SFCs', () => {
        const result = transform(
            'import.meta.hot.accept((mod) => { __VUE_HMR_RUNTIME__.reload(mod.default.__hmrId, mod.default); });',
            '/extension/sw-product-detail.override.vue.shopware-setup.vue',
        );

        expect(result?.code).toContain('runtime.rerender(component.__hmrId, component.render)');
    });

    it('attaches script ownership to the actual default export with a sourcemap', () => {
        const result = transform(
            'const component = {}; export default component; import.meta.hot.accept(() => {});',
            '/extension/sw-example.vue.shopware-setup.vue',
            { componentName: 'sw-example', mode: 'override', version: 'script:1' },
        );
        expect(result?.code).toContain('const __swSetupHmrComponent = component;');
        expect(result?.code).toContain('__swSetupHmrComponent.__shopwareSetupHmr');
        expect(result?.code).toContain('"version":"script:1"');
        expect(result?.code).toContain('import.meta.hot.invalidate(');
        expect(result?.map).toBeDefined();
    });

    it('preserves author handlers inside setup and dependency accept callbacks', () => {
        const result = transform(
            `
            const component = { setup() { import.meta.hot.accept(() => 'author handler'); } };
            import.meta.hot.accept('./dependency', () => 'dependency handler');
            export default component;
            import.meta.hot.accept((mod) => 'Vue handler');
        `,
            '/extension/sw-example.vue.shopware-setup.vue',
            { componentName: 'sw-example', mode: 'base', version: '1' },
        );
        expect(result?.code).toContain("import.meta.hot.accept(() => 'author handler')");
        expect(result?.code).toContain("import.meta.hot.accept('./dependency', () => 'dependency handler')");
        expect(result?.code).not.toContain("(mod) => 'Vue handler'");
    });

    it('isolates identical Vue IDs from different extension roots while keeping one source stable', () => {
        const code =
            'const component = {}; component.__hmrId = "same-vue-id"; export default component; import.meta.hot.accept(() => {});';
        const metadata = { componentName: 'sw-dashboard-index', mode: 'override', version: '1' };
        const first = transform(code, '/extensions/first/sw-dashboard-index.override.vue.shopware-setup.vue', metadata);
        const second = transform(code, '/extensions/second/sw-dashboard-index.override.vue.shopware-setup.vue', metadata);
        const repeat = transform(code, '/extensions/first/sw-dashboard-index.override.vue.shopware-setup.vue', metadata);
        const windows = transform(code, '\\extensions\\first\\sw-dashboard-index.override.vue.shopware-setup.vue', metadata);
        const id = (output: typeof first) => output?.code.match(/component\.__hmrId = "([^"]+)"/)?.[1];
        expect(id(first)).toMatch(/^shopware-setup:/);
        expect(id(first)).not.toBe(id(second));
        expect(id(first)).toBe(id(repeat));
        expect(id(first)).toBe(id(windows));
    });

    it.each([
        ['script:1', false, false],
        ['script:2', true, false],
        ['script:1:helper:2', true, false],
        ['script:2', false, true],
        ['script:1', false, true],
    ])(
        'classifies the accepted module version %s before choosing reload or rerender',
        (version, shouldReload, reloadPage) => {
            const result = transform(
                `
                const component = { __hmrId: 'script-boundary', render() {} };
                export default component;
                if (import.meta.hot) import.meta.hot.accept(() => { throw new Error('old Vue classification'); });
            `,
                '/extension/sw-example.vue.shopware-setup.vue',
                { componentName: 'sw-example', mode: 'override', version: 'script:1' },
            );
            const hot = {
                accept: jest.fn<void, [(module: unknown) => void]>(),
                invalidate: jest.fn(),
                data: { refreshParent: jest.fn(), updateDefinitions: jest.fn(), reloadPage },
            };
            const runtime = (
                globalThis as typeof globalThis & {
                    __VUE_HMR_RUNTIME__: { reload: () => void; rerender: () => void };
                }
            ).__VUE_HMR_RUNTIME__;
            const reload = jest.spyOn(runtime, 'reload').mockImplementation(() => {});
            const rerender = jest.spyOn(runtime, 'rerender').mockImplementation(() => {});
            try {
                expect(result).not.toBeNull();
                if (!result) return;
                const executable = result.code
                    .replaceAll('import.meta.hot', 'hot')
                    .replace('export default __swSetupHmrComponent;', '');
                // Execute the generated self-accept boundary; Vue's old cached descriptor must not decide.
                const sandbox = { hot, globalThis: {} as { __VUE_HMR_RUNTIME__?: typeof runtime } };
                sandbox.globalThis.__VUE_HMR_RUNTIME__ = runtime;
                new vm.Script(executable).runInNewContext(sandbox);
                expect(hot.accept).toHaveBeenCalledTimes(1);
                const accept = hot.accept.mock.calls[0][0] as (module: unknown) => void;
                accept(undefined);
                expect(reload).not.toHaveBeenCalled();
                expect(rerender).not.toHaveBeenCalled();
                accept({ default: { __hmrId: 'script-boundary', render: jest.fn(), __shopwareSetupHmr: { version } } });
                expect(reload).toHaveBeenCalledTimes(shouldReload ? 1 : 0);
                expect(hot.data.refreshParent).toHaveBeenCalledTimes(shouldReload ? 1 : 0);
                expect(hot.data.updateDefinitions).toHaveBeenCalledTimes(reloadPage ? 0 : 1);
                expect(rerender).toHaveBeenCalledTimes(reloadPage ? 0 : 1);
                expect(hot.invalidate).toHaveBeenCalledTimes(reloadPage ? 1 : 0);
                delete sandbox.globalThis.__VUE_HMR_RUNTIME__;
                hot.invalidate.mockClear();
                reload.mockClear();
                rerender.mockClear();
                accept({ default: { __hmrId: 'script-boundary' } });
                expect(hot.invalidate).toHaveBeenCalledTimes(1);
                expect(reload).not.toHaveBeenCalled();
                expect(rerender).not.toHaveBeenCalled();
            } finally {
                reload.mockRestore();
                rerender.mockRestore();
            }
        },
    );

    it.each([
        ['/extension/sw-product-detail.vue', 'import.meta.hot.accept(() => {})'],
        ['/extension/sw-product-detail.override.vue.shopware-setup.vue', 'export default {}'],
    ])('leaves unsupported module %s unchanged', (id, code) => {
        expect(transform(code, id)).toBeNull();
    });
});
