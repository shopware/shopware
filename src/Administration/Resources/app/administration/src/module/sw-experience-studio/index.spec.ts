import './index';

jest.mock('./acl', () => jest.fn());

/** @sw-package discovery */
describe('Experience Studio workspace routes', () => {
    it('registers a loadable grid editor', async () => {
        const loadEditor = Shopware.Component.getComponentRegistry().get('sw-experience-studio-grid-editor');

        expect(loadEditor).toBeDefined();
        expect(await loadEditor?.()).toBeTruthy();
    });

    it('uses the workspace canvas editor for every entry route', () => {
        const routes = Shopware.Module.getModuleRegistry().get('sw-experience-studio')?.routes;

        expect(routes?.size).toBe(3);
        for (const name of ['index', 'workspace', 'detail']) {
            expect(routes?.get(`sw.experience.studio.${name}`)?.components).toEqual({
                default: 'sw-experience-studio-detail',
            });
        }
        expect(routes?.has('sw.experience.studio.create')).toBe(false);
    });
});
