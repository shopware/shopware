import createRouter from 'src/core/factory/router.factory';


describe('core/factory/router.factory.js', () => {
    it('should generate default favicon path', () => {
        const moduleRegistry = {
            getModuleRegistry: () => {
                return [
                    {
                        routes: new Map([['sw-test-route', {}]]),
                        manifest: {
                            favicon: 'icon-test.png'
                        }
                    }
                ];
            }
        };
        const router = createRouter({}, {}, moduleRegistry, {});

        const element = {};
        jest.spyOn(document, 'getElementById')
            .mockImplementation(() => element);

        const faviconCreated =
            router._setModuleFavicon({ name: 'sw-test-route' }, 'http://localhost/public/bundles/');

        expect(faviconCreated).toBe(true);
        expect(element.rel).toEqual('shortcut icon');
        expect(element.href).toEqual('http://localhost/public/bundles/administration/static/img/favicon/modules/icon-test.png');
    });

    it('should generate plugin favicon path', () => {
        const moduleRegistry = {
            getModuleRegistry: () => {
                return [
                    {
                        routes: new Map([['sw-test-route', {}]]),
                        manifest: {
                            favicon: 'icon-test.png',
                            faviconSrc: 'swagplugin'
                        }
                    }
                ];
            }
        };
        const router = createRouter({}, {}, moduleRegistry, {});

        const element = {};
        jest.spyOn(document, 'getElementById')
            .mockImplementation(() => element);

        const faviconCreated =
            router._setModuleFavicon({ name: 'sw-test-route' }, 'http://localhost/public/bundles/');

        expect(faviconCreated).toBe(true);
        expect(element.rel).toEqual('shortcut icon');
        expect(element.href).toEqual('http://localhost/public/bundles/swagplugin/static/img/favicon/modules/icon-test.png');
    });
});
