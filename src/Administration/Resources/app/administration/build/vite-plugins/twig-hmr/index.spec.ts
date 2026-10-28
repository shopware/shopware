/**
 * @sw-package framework
 */
import TwigHmrPlugin from './index';

type TransformResult = { code: string; map: unknown } | null;
type TransformContext = { warn: jest.Mock; resolve: jest.Mock };
type TransformHook = (this: TransformContext, code: string, id: string) => Promise<TransformResult> | TransformResult;

function createTransform(plugin = TwigHmrPlugin()) {
    const warn = jest.fn();
    const resolve = jest.fn();
    const hook = plugin.transform as unknown as TransformHook;

    return {
        plugin,
        transform: (code: string, id = '/plugins/example/src/main.ts') => hook.call({ warn, resolve }, code, id),
        warn,
        resolve,
    };
}

describe('build/vite-plugins/twig-hmr', () => {
    it('runs only in the Vite dev server before other transforms', () => {
        const plugin = TwigHmrPlugin();

        expect(plugin.name).toBe('shopware-vite-plugin-twig-hmr');
        expect(plugin.apply).toBe('serve');
        expect(plugin.enforce).toBe('pre');
    });

    it('adds a stable Twig identity and accepts an imported override template', async () => {
        const { transform } = createTransform();
        const result = await transform(
            `
import template from './views/view.twig';
Shopware.Component.override('sw-example-detail', { template });
`,
            '/plugins/example/src/main.ts',
        );

        expect(result?.code).toContain('__shopwareTwigHmrId: "/plugins/example/src/views/view.twig#config=');
        expect(result?.code).toContain('import.meta.hot.accept("./views/view.twig"');
        expect(result?.code).toContain('updateComponentTemplateFromHmr("sw-example-detail"');
        expect(result?.code).toContain('import.meta.hot.invalidate');
        expect(result?.map).not.toBeNull();
    });

    it('creates separate handlers for multiple component overrides using one Twig file', async () => {
        const { transform } = createTransform();
        const result = await transform(`
import template from './views/shared.twig';
Shopware.Component.override('sw-example-first', { template });
Shopware.Component.override('sw-example-second', { template });
`);

        expect(result?.code.match(/__shopwareTwigHmrId:/g)).toHaveLength(2);
        expect(result?.code.match(/import\.meta\.hot\.accept\("\.\/views\/shared\.twig"/g)).toHaveLength(2);
        expect(result?.code).toContain('updateComponentTemplateFromHmr("sw-example-first"');
        expect(result?.code).toContain('updateComponentTemplateFromHmr("sw-example-second"');
    });

    it('supports the SwagCommercial lazy Twig-backed component pattern', async () => {
        const { transform, resolve } = createTransform();
        const entryId = '/plugins/SwagCommercial/src/ContentGenerator/Resources/app/administration/src/main.ts';
        const componentId =
            '/plugins/SwagCommercial/src/ContentGenerator/Resources/app/administration/src/module/sw-cms/elements/text/component/index.ts';
        resolve.mockResolvedValue({ id: componentId });

        await transform(
            `Shopware.Component.override('sw-cms-el-text', () => import('./module/sw-cms/elements/text/component'));`,
            entryId,
        );

        const component = await transform(
            `
import template from './sw-cms-el-text.html.twig';
const { Component, Mixin } = Shopware;
export default Component.wrapComponentConfig({ template, mixins: [Mixin.getByName('notification')] });
`,
            componentId,
        );

        expect(component?.code).toContain(
            '__shopwareTwigHmrId: "/plugins/SwagCommercial/src/ContentGenerator/Resources/app/administration/src/module/sw-cms/elements/text/component/sw-cms-el-text.html.twig#config=',
        );
        expect(component?.code).toContain('import.meta.hot.accept("./sw-cms-el-text.html.twig"');
        expect(component?.code).toContain('updateComponentTemplateFromHmr("sw-cms-el-text"');
        expect(resolve).toHaveBeenCalledWith('./module/sw-cms/elements/text/component', entryId, { skipSelf: true });
    });

    it('leaves dynamic component names to Vite default HMR behavior', async () => {
        const { transform } = createTransform();
        const result = await transform(`
import template from './views/detail.twig';
Shopware.Component.override(componentName, { template });
`);

        expect(result).toBeNull();
    });

    it('does not transform modules without a static Twig-backed component override', async () => {
        const { transform } = createTransform();
        const result = await transform(`
import template from './views/detail.twig';
Shopware.Component.register('sw-example-detail', { template });
`);

        expect(result).toBeNull();
    });
});
