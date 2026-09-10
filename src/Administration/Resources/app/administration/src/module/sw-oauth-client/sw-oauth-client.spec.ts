/**
 * @sw-package framework
 */
import 'src/module/sw-oauth-client';

jest.mock('./acl', () => ({}));

describe('OAuth application module', () => {
    it('protects both the route and settings navigation', () => {
        const module = Shopware.Module.getModuleRegistry().get('sw-oauth-client');
        expect(module?.routes?.get('sw.oauth.client.index')?.meta).toEqual(
            expect.objectContaining({ privilege: 'oauth_client.viewer' }),
        );
        expect(module?.manifest.settingsItem).toEqual(
            expect.arrayContaining([expect.objectContaining({ privilege: 'oauth_client.viewer' })]),
        );
        expect(Shopware.Component.getComponentRegistry().has('sw-oauth-client-list')).toBe(true);
    });
});
