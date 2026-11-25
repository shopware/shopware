/**
 * @sw-package framework
 */
import './index';

jest.mock('./acl', () => ({}));

describe('src/module/sw-settings-webhook', () => {
    it('protects the list and its settings tile with the viewer role', () => {
        const module = Shopware.Module.getModuleRegistry().get('sw-settings-webhook');

        expect(module?.routes.get('sw.settings.webhook.index')?.meta).toEqual(
            expect.objectContaining({ privilege: 'webhook.viewer' }),
        );
        expect(module?.manifest.settingsItem).toEqual([expect.objectContaining({ privilege: 'webhook.viewer' })]);
    });
});
