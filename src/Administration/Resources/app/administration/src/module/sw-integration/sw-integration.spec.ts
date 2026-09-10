/**
 * @sw-package framework
 */
import 'src/module/sw-integration';

jest.mock('./acl', () => ({}));
jest.mock('./acl/oauth-client', () => ({}));

describe('Integration module navigation', () => {
    afterEach(() => jest.restoreAllMocks());

    it('keeps independent route permissions within the same module', () => {
        const module = Shopware.Module.getModuleRegistry().get('sw-integration');
        expect(module?.routes?.get('sw.integration.index')?.meta).toEqual(
            expect.objectContaining({ privilege: 'integration.viewer' }),
        );
        expect(module?.routes?.get('sw.integration.oauth')?.meta).toEqual(
            expect.objectContaining({ privilege: 'oauth_client.viewer' }),
        );
        expect(Shopware.Module.getModuleRegistry().has('sw-oauth-client')).toBe(false);
        expect(Shopware.Component.getComponentRegistry().has('sw-oauth-client-list')).toBe(true);
    });

    it.each([
        [
            ['integration.viewer'],
            'sw.integration.index',
            true,
        ],
        [
            ['oauth_client.viewer'],
            'sw.integration.oauth',
            true,
        ],
        [
            [
                'integration.viewer',
                'oauth_client.viewer',
            ],
            'sw.integration.index',
            true,
        ],
        [
            [],
            'sw.integration.oauth',
            false,
        ],
    ])('resolves one Settings entry for privileges %j', (privileges, route, visible) => {
        jest.spyOn(Shopware.Service('acl'), 'can').mockImplementation((key) => privileges.includes(key));
        const entries = Shopware.Store.get('settingsItems').settingsGroups.system.filter(
            (item) => item.name === 'integration',
        );
        expect(entries).toHaveLength(1);
        const entry = entries[0] as unknown as { to: string; privilege: string };
        expect(entry.to).toBe(route);
        expect(Shopware.Service('acl').can(entry.privilege)).toBe(visible);
    });
});
