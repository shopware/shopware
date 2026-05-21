/**
 * @sw-package fundamentals@framework
 */

describe('module/sw-settings-ucp', () => {
    beforeAll(async () => {
        // `./acl` registers privileges via Shopware.Service('privileges'). The default
        // test bootstrap does not provide that service, so the module bootstrap would
        // otherwise crash before the module ever reaches the registry. Stub it for
        // the test scope (the acl/index.spec.js already pins the privilege content).
        const originalService = Shopware.Service;
        Shopware.Service = jest.fn((key) => {
            if (key === 'privileges') {
                return { addPrivilegeMappingEntry: jest.fn() };
            }
            return originalService(key);
        });

        await import('./');
    });

    it('registers the sw-settings-ucp module with two routes', () => {
        const module = Shopware.Module.getModuleRegistry().get('sw-settings-ucp');

        expect(module).toBeDefined();
        expect(module.manifest.name).toBe('settings-ucp');
        expect(module.manifest.type).toBe('core');
        expect(module.manifest.routes.index.component).toBe('sw-settings-ucp-index');
        expect(module.manifest.routes.detail.component).toBe('sw-settings-ucp-detail');
    });

    it('gates both routes behind the ucp.viewer ACL privilege', () => {
        const module = Shopware.Module.getModuleRegistry().get('sw-settings-ucp');

        expect(module.manifest.routes.index.meta.privilege).toBe('ucp.viewer');
        expect(module.manifest.routes.detail.meta.privilege).toBe('ucp.viewer');
    });

    it('hides the settings entry until the UCP_SERVER feature flag is active', () => {
        const module = Shopware.Module.getModuleRegistry().get('sw-settings-ucp');

        expect(module.manifest.settingsItem.flag).toBe('UCP_SERVER');
        expect(module.manifest.settingsItem.group).toBe('system');
        expect(module.manifest.settingsItem.privilege).toBe('ucp.viewer');
    });

    it('passes the detail route salesChannelId param through props', () => {
        const module = Shopware.Module.getModuleRegistry().get('sw-settings-ucp');
        const propsResolver = module.manifest.routes.detail.props.default;

        expect(propsResolver({ params: { salesChannelId: 'sc-42' } })).toEqual({ salesChannelId: 'sc-42' });
    });
});
