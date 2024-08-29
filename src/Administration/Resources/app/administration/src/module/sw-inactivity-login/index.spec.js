/**
 * @package admin
 * @group disabledCompat
 */
import 'src/module/sw-inactivity-login/index';

describe('src/module/sw-inactivity-login/page/index.ts', () => {
    it('should register module', () => {
        const module = Shopware.Module.getModuleRegistry().get('sw-inactivity-login');

        expect(module !== undefined).toBe(true);
        expect(module.manifest.type).toBe('core');
        expect(module.manifest.name).toBe('inactivity-login');
        expect(module.routes.size).toBe(1);

        const route = module.routes.get('sw.inactivity.login.index');
        expect(route !== undefined).toBe(true);
        expect(route.path).toBe('/inactivity/login/:id');

        const props = route.props.default({ params: { id: 'foo' } });
        expect(props.hasOwnProperty('hash')).toBe(true);
        expect(props.hash).toBe('foo');
    });
});
