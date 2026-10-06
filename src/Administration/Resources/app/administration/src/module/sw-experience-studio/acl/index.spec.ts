/**
 * @sw-package discovery
 */

type PrivilegeEntry = {
    category: string;
    parent: string | null;
    key: string;
    roles: Record<string, { privileges: string[]; dependencies: string[] }>;
};

const privileges = {
    addPrivilegeMappingEntry: jest.fn<unknown, [PrivilegeEntry]>(),
};

const originalShopwareService = Shopware.Service;

describe('src/module/sw-experience-studio/acl/index.ts', () => {
    beforeAll(() => {
        // The module chains its two registrations, so the mock returns itself.
        Shopware.Service = (() => privileges) as unknown as typeof Shopware.Service;
    });

    beforeEach(async () => {
        jest.resetAllMocks();
        jest.resetModules();
        privileges.addPrivilegeMappingEntry.mockReturnValue(privileges);

        await import('./index');
    });

    afterAll(() => {
        Shopware.Service = originalShopwareService;
    });

    it('registers exactly the permission entry and the additional permission entry', () => {
        const [first, second] = privileges.addPrivilegeMappingEntry.mock.calls.map(([entry]) => entry);

        expect(privileges.addPrivilegeMappingEntry).toHaveBeenCalledTimes(2);
        expect(first).toMatchObject({ category: 'permissions', parent: 'content', key: 'experience_studio' });
        expect(second).toMatchObject({ category: 'additional_permissions', parent: null, key: 'experience_studio' });
    });

    it('lets the editor role translate content layouts', () => {
        const { editor } = privileges.addPrivilegeMappingEntry.mock.calls[0][0].roles;

        expect(editor.privileges).toContain('content_layout:translate');
    });

    it('registers a translator role holding only the translate privilege on top of the viewer', () => {
        const { translator } = privileges.addPrivilegeMappingEntry.mock.calls[1][0].roles;

        expect(translator.privileges).toEqual(['content_layout:translate']);
        expect(translator.dependencies).toEqual(['experience_studio.viewer']);
    });
});
