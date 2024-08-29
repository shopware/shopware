/**
 * @package admin
 * @group disabledCompat
 */
import initializeRouter from 'src/app/init/router.init';

describe('src/app/init/router.init.ts', () => {
    it('should initialize the router', () => {
        const result = initializeRouter(Shopware.Application.getContainer('init'));

        expect(result).toHaveProperty('addRoutes');
        expect(result).toHaveProperty('addModuleRoutes');
        expect(result).toHaveProperty('createRouterInstance');
        expect(result).toHaveProperty('getViewComponent');
        expect(result).toHaveProperty('getRouterInstance');
        expect(result).toHaveProperty('_setModuleFavicon');
    });
});
