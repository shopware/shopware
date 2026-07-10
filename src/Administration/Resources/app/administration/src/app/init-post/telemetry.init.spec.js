import initProductAnalytics from './telemetry.init';

describe('src/app/init-post/telemetry.init.ts', () => {
    it('calls Telemetry.init', async () => {
        jest.spyOn(Shopware.Telemetry, 'initialize');

        await initProductAnalytics();

        expect(Shopware.Telemetry.initialize).toHaveBeenCalled();
    });
});
