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
        const stateModules = [
            'notification',
            'session',
            'system',
            'adminMenu',
            'licenseViolation',
            'context',
            'error',
            'settingsItems',
            'shopwareApps',
            'extensionEntryRoutes',
            'marketing',
            'extensionComponentSections',
            'extensions',
            'tabs',
            'menuItem',
            'extensionSdkModules',
            'modals',
            'extensionMainModules',
            'actionButtons',
            'ruleConditionsConfig',
            'sdkLocation',
            'usageData',
            'adminHelpCenter',
        ];

        expect(Shopware.State.list()).toHaveLength(stateModules.length);

        stateModules.forEach((moduleName) => {
            expect(Shopware.State.get(moduleName)).toBeDefined();
        });
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
            id: 'cmsPage',
            state: () => ({
                foo: 'bar',
            }),
        });

        expect(Shopware.Store.get('cmsPage').foo).toBe('bar');
        Shopware.Store.unregister('cmsPage');
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
            id: 'cmsPage',
            state: () => ({
                foo: 'bar',
            }),
            actions: {
                setFoo(foo) {
                    this.foo = foo;
                },
            },
        });

        const store = Shopware.Store.get('cmsPage');
        expect(store.foo).toBe('bar');

        store.setFoo('jest');
        expect(store.foo).toBe('jest');

        Shopware.Store.unregister('cmsPage');
    });
});
