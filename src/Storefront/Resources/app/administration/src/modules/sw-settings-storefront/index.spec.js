/**
 * @sw-package framework
 */
describe('sw-settings-storefront module', () => {
    it('registers storefront settings module', () => {
        const registerSpy = jest.spyOn(Shopware.Module, 'register');

        jest.isolateModules(() => {
            require('./index');
        });

        expect(registerSpy).toHaveBeenCalledWith('sw-settings-storefront', expect.objectContaining({
            routes: expect.objectContaining({
                index: expect.objectContaining({
                    components: expect.objectContaining({ default: 'sw-settings-storefront-index' }),
                }),
            }),
            settingsItem: expect.arrayContaining([
                expect.objectContaining({
                    to: 'sw.settings.storefront.index',
                    privilege: 'system.system_config',
                }),
            ]),
        }));
    });
});
