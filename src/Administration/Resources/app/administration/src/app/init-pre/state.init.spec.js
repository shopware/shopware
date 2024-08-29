/**
 * @package admin
 */
import initState from 'src/app/init-pre/state.init';

describe('src/app/init-pre/state.init.ts', () => {
    initState();

    it('should contain all state methods', () => {
        expect(Shopware.State._store).toBeDefined();
        expect(Shopware.State.list).toBeDefined();
        expect(Shopware.State.get).toBeDefined();
        expect(Shopware.State.getters).toBeDefined();
        expect(Shopware.State.commit).toBeDefined();
        expect(Shopware.State.dispatch).toBeDefined();
        expect(Shopware.State.watch).toBeDefined();
        expect(Shopware.State.subscribe).toBeDefined();
        expect(Shopware.State.subscribeAction).toBeDefined();
        expect(Shopware.State.registerModule).toBeDefined();
        expect(Shopware.State.unregisterModule).toBeDefined();
    });

    it('should initialized all state modules', () => {
        expect(Shopware.State.list()).toHaveLength(23);

        expect(Shopware.State.get('notification')).toBeDefined();
        expect(Shopware.State.get('session')).toBeDefined();
        expect(Shopware.State.get('system')).toBeDefined();
        expect(Shopware.State.get('adminMenu')).toBeDefined();
        expect(Shopware.State.get('licenseViolation')).toBeDefined();
        expect(Shopware.State.get('context')).toBeDefined();
        expect(Shopware.State.get('error')).toBeDefined();
        expect(Shopware.State.get('settingsItems')).toBeDefined();
        expect(Shopware.State.get('shopwareApps')).toBeDefined();
        expect(Shopware.State.get('extensionEntryRoutes')).toBeDefined();
        expect(Shopware.State.get('marketing')).toBeDefined();
        expect(Shopware.State.get('extensionComponentSections')).toBeDefined();
        expect(Shopware.State.get('extensions')).toBeDefined();
        expect(Shopware.State.get('tabs')).toBeDefined();
        expect(Shopware.State.get('menuItem')).toBeDefined();
        expect(Shopware.State.get('extensionSdkModules')).toBeDefined();
        expect(Shopware.State.get('modals')).toBeDefined();
        expect(Shopware.State.get('extensionMainModules')).toBeDefined();
        expect(Shopware.State.get('actionButtons')).toBeDefined();
        expect(Shopware.State.get('ruleConditionsConfig')).toBeDefined();
        expect(Shopware.State.get('sdkLocation')).toBeDefined();
        expect(Shopware.State.get('usageData')).toBeDefined();
        expect(Shopware.State.get('adminHelpCenter')).toBeDefined();
    });

    it('should be able to get cmsPageState backwards compatible', () => {
        // The cmsPageState is deprecated and causes a warning, therefore ignore it
        global.allowedErrors.push({
            method: 'warn',
            msgCheck: (_, msg) => {
                if (typeof msg !== 'string') {
                    return false;
                }

                return msg === 'Shopware.State.get("cmsPageState") is deprecated! Use Shopware.Store.get instead.';
            },
        });

        Shopware.Store.register({
            id: 'cmsPageState',
            state: () => ({
                foo: 'bar',
            }),
        });

        expect(Shopware.State.get('cmsPageState').foo).toBe('bar');
        Shopware.Store.unregister('cmsPageState');
    });

    it('should be able to commit cmsPageState backwards compatible', () => {
        // The cmsPageState is deprecated and causes a warning, therefore ignore it
        global.allowedErrors.push({
            method: 'warn',
            msgCheck: (_, msg) => {
                if (typeof msg !== 'string') {
                    return false;
                }

                return msg === 'Shopware.State.get("cmsPageState") is deprecated! Use Shopware.Store.get instead.';
            },
        });

        Shopware.Store.register({
            id: 'cmsPageState',
            state: () => ({
                foo: 'bar',
            }),
            actions: {
                setFoo(foo) {
                    this.foo = foo;
                },
            },
        });

        const store = Shopware.Store.get('cmsPageState');
        expect(store.foo).toBe('bar');

        Shopware.State.commit('cmsPageState/setFoo', 'jest');
        expect(store.foo).toBe('jest');

        Shopware.Store.unregister('cmsPageState');
    });
});
