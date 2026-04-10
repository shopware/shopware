/**
 * @sw-package discovery
 */
import PrivilegesService from 'src/app/service/privileges.service';

describe('sw-theme-manager module', () => {
    beforeAll(() => {
        Shopware.Application.addServiceProvider('privileges', () => new PrivilegesService());

        jest.isolateModules(() => {
            require('./index');
        });
    });

    it('registers module with routes and navigation', () => {
        const module = Shopware.Module.getModuleRegistry().get('sw-theme-manager');

        expect(module).toBeDefined();
        expect(module.manifest.navigation).toEqual(expect.arrayContaining([
            expect.objectContaining({ id: 'sw-theme-manager', path: 'sw.theme.manager.index' }),
        ]));

        const routes = module.routes;
        expect(routes.get('sw.theme.manager.index').components.default).toBe('sw-theme-manager-list');
        expect(routes.get('sw.theme.manager.detail').components.default).toBe('sw-theme-manager-detail');
    });

    it('adds sales channel detail theme route when missing', () => {
        const module = Shopware.Module.getModuleRegistry().get('sw-theme-manager');
        const routeMiddleware = module.manifest.routeMiddleware;
        const next = jest.fn();
        const currentRoute = {
            name: 'sw.sales.channel.detail',
            children: [],
        };

        routeMiddleware(next, currentRoute);

        expect(currentRoute.children).toHaveLength(1);
        expect(currentRoute.children[0]).toEqual(expect.objectContaining({
            name: 'sw.sales.channel.detail.theme',
            component: 'sw-sales-channel-detail-theme',
        }));
        expect(next).toHaveBeenCalledWith(currentRoute);
    });

    it('does not duplicate sales channel detail theme route', () => {
        const module = Shopware.Module.getModuleRegistry().get('sw-theme-manager');
        const routeMiddleware = module.manifest.routeMiddleware;
        const currentRoute = {
            name: 'sw.sales.channel.detail',
            children: [
                { name: 'sw.sales.channel.detail.theme' },
            ],
        };

        routeMiddleware(jest.fn(), currentRoute);

        expect(currentRoute.children).toHaveLength(1);
    });
});
