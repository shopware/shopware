import PrivilegesService from 'src/app/service/privileges.service';

/**
 * @sw-package discovery
 */
describe('src/module/sw-experience-studio/acl/index.ts', () => {
    let privileges: PrivilegesService;
    let service: jest.SpyInstance;

    beforeEach(() => {
        privileges = new PrivilegesService();
        service = jest
            .spyOn(Shopware, 'Service')
            .mockImplementation((() => privileges) as unknown as typeof Shopware.Service);

        jest.isolateModules(() => {
            jest.requireActual('./index');
        });
    });

    afterEach(() => {
        service.mockRestore();
    });

    it('lets viewers read landing pages and editors manage their layout assignments', () => {
        const viewer = privileges.getPrivilegeRole('experience_studio.viewer');
        const editor = privileges.getPrivilegeRole('experience_studio.editor');

        expect(viewer?.privileges).toContain('landing_page:read');
        expect(viewer?.privileges).toContain('landing_page_content_layout:read');
        expect(viewer?.privileges).not.toContain('user:read');
        expect(viewer?.privileges).not.toContain('media:read');
        expect(viewer?.privileges).toContain('content_workspace:read');
        for (const [operation, role] of [
            ['create', 'editor'],
            ['update', 'editor'],
            ['delete', 'deleter'],
        ]) {
            expect(viewer?.privileges).not.toContain(`content_workspace:${operation}`);
            expect(privileges.getPrivilegeRole(`experience_studio.${role}`)?.privileges).toContain(
                `content_workspace:${operation}`,
            );
        }
        expect(editor?.privileges).not.toContain('content_layout:create');
        for (const operation of ['create', 'update', 'delete']) {
            expect(editor?.privileges).toContain(`landing_page_content_layout:${operation}`);
            expect(viewer?.privileges).not.toContain(`landing_page_content_layout:${operation}`);
        }
        expect(editor?.dependencies).toContain('experience_studio.viewer');
    });

    it('registers exactly the permission entry and the additional permission entry', () => {
        const entries = privileges
            .getPrivilegesMappings()
            .map(({ category, parent, key }) => ({ category, parent, key }));

        expect(entries).toEqual([
            { category: 'permissions', parent: 'content', key: 'experience_studio' },
            { category: 'additional_permissions', parent: null, key: 'experience_studio' },
        ]);
    });

    it('lets the editor role translate content layouts', () => {
        expect(privileges.getPrivilegeRole('experience_studio.editor')?.privileges).toContain('content_layout:translate');
    });

    it('registers a translator role holding only the translate privilege on top of the viewer', () => {
        const translator = privileges.getPrivilegeRole('experience_studio.translator');

        expect(translator?.privileges).toEqual(['content_layout:translate']);
        expect(translator?.dependencies).toEqual(['experience_studio.viewer']);
    });
});
