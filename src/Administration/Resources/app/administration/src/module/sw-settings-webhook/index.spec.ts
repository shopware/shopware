/**
 * @sw-package framework
 */
import './index';

jest.mock('./acl', () => ({}));

describe('src/module/sw-settings-webhook', () => {
    it('registers the list page and the deliveries modal', () => {
        expect(Shopware.Component.getComponentRegistry().has('sw-settings-webhook-list')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-settings-webhook-deliveries-modal')).toBe(true);
    });

    it('registers the module next to the integrations in the system settings', () => {
        const module = Shopware.Module.getModuleRegistry().get('sw-settings-webhook');

        expect(module?.manifest.settingsItem).toEqual([
            expect.objectContaining({
                group: 'system',
                to: 'sw.settings.webhook.index',
                privilege: 'webhook.viewer',
            }),
        ]);
    });

    it('protects the list route with the viewer role', () => {
        const route = Shopware.Module.getModuleRegistry()
            .get('sw-settings-webhook')
            ?.routes.get('sw.settings.webhook.index');

        expect(route?.path).toBe('/sw/settings/webhook/index');
        expect(route?.meta).toEqual({
            parentPath: 'sw.settings.index.system',
            privilege: 'webhook.viewer',
        });
    });
});
