/**
 * @sw-package fundamentals@framework
 */

describe('module/sw-settings-ucp/acl', () => {
    let captured;

    beforeAll(async () => {
        captured = null;
        const addPrivilegeMappingEntry = jest.fn((entry) => {
            captured = entry;
        });
        Shopware.Service = jest.fn(() => ({ addPrivilegeMappingEntry }));

        await import('./');
    });

    it('registers the ucp privilege mapping under settings', () => {
        expect(captured).toBeDefined();
        expect(captured.category).toBe('permissions');
        expect(captured.parent).toBe('settings');
        expect(captured.key).toBe('ucp');
        expect(Object.keys(captured.roles)).toEqual([
            'viewer',
            'editor',
            'key_rotator',
        ]);
    });

    it('grants the viewer role read access to all four UCP entities plus the sales channel context', () => {
        expect(captured.roles.viewer.privileges).toEqual(
            expect.arrayContaining([
                'sales_channel:read',
                'sales_channel_domain:read',
                'ucp_sales_channel_config:read',
                'ucp_signing_key:read',
                'ucp_platform_profile_cache:read',
                'ucp_negotiation_session:read',
            ]),
        );
        expect(captured.roles.viewer.dependencies).toEqual([]);
    });

    it('grants the editor role config create/update plus profile-cache delete and depends on viewer', () => {
        expect(captured.roles.editor.privileges).toEqual(
            expect.arrayContaining([
                'ucp_sales_channel_config:create',
                'ucp_sales_channel_config:update',
                'ucp_platform_profile_cache:delete',
            ]),
        );
        expect(captured.roles.editor.dependencies).toEqual(['ucp.viewer']);
    });

    it('does NOT grant the editor role any ucp_signing_key write privileges', () => {
        expect(captured.roles.editor.privileges).not.toContain('ucp_signing_key:create');
        expect(captured.roles.editor.privileges).not.toContain('ucp_signing_key:update');
        expect(captured.roles.editor.privileges).not.toContain('ucp_signing_key:delete');
    });

    it('grants the key_rotator role signing-key create/update/delete and depends on editor', () => {
        expect(captured.roles.key_rotator.privileges).toEqual(
            expect.arrayContaining([
                'ucp_signing_key:create',
                'ucp_signing_key:update',
                'ucp_signing_key:delete',
            ]),
        );
        expect(captured.roles.key_rotator.dependencies).toEqual(['ucp.editor']);
    });
});
