import PrivilegesService from 'src/app/service/privileges.service';

/**
 * @sw-package discovery
 */
describe('Experience Studio landing page assignment permissions', () => {
    it('lets viewers read landing pages and editors manage their layout assignments', () => {
        const privileges = new PrivilegesService();
        const service = jest
            .spyOn(Shopware, 'Service')
            .mockImplementation((() => privileges) as unknown as typeof Shopware.Service);

        try {
            jest.requireActual('./index');

            const viewer = privileges.getPrivilegeRole('experience_studio.viewer');
            const editor = privileges.getPrivilegeRole('experience_studio.editor');

            expect(viewer?.privileges).toContain('landing_page:read');
            expect(viewer?.privileges).toContain('landing_page_content_layout:read');
            for (const operation of ['create', 'update', 'delete']) {
                expect(editor?.privileges).toContain(`landing_page_content_layout:${operation}`);
                expect(viewer?.privileges).not.toContain(`landing_page_content_layout:${operation}`);
            }
            expect(editor?.dependencies).toContain('experience_studio.viewer');
        } finally {
            service.mockRestore();
        }
    });
});
