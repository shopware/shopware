import registerAsyncComponents from 'src/app/asyncComponent/asyncComponents';

describe('src/app/asyncComponent/asyncComponent', () => {
    it('should register the components asynchronously', async () => {
        expect(Shopware.Component.getComponentRegistry().size).toBe(0);

        await registerAsyncComponents();

        expect(Shopware.Component.getComponentRegistry().has('sw-code-editor')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-chart')).toBe(true);
    });
});
