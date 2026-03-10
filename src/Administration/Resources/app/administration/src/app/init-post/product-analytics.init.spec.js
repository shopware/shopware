import initProductAnalytics from './product-analytics.init';

describe('src/app/init-post/product-analytics.init.ts', () => {
    it('calls Telemetry.init', async () => {
        jest.spyOn(Shopware.Telemetry, 'initialize');

        await initProductAnalytics();

        expect(Shopware.Telemetry.initialize).toHaveBeenCalled();
    });
});
