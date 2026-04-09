/**
 * @sw-package framework
 */

import initializeWindow, { _windowLocationHelpers } from 'src/app/init/window.init';
import { send } from '@shopware-ag/meteor-admin-sdk/es/channel';

describe('src/app/init/window.init.ts', () => {
    beforeAll(() => {
        initializeWindow();
        window.open = jest.fn();
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('should handle windowReload', async () => {
        const reloadSpy = jest.spyOn(_windowLocationHelpers, 'reload').mockImplementation(() => {});

        await send('windowReload');

        expect(reloadSpy).toHaveBeenCalled();
    });

    it('should handle windowRedirect', async () => {
        const navigateSpy = jest.spyOn(_windowLocationHelpers, 'navigate').mockImplementation(() => {});

        await send('windowRedirect', {
            url: 'http://example.com',
            newTab: false,
        });

        expect(navigateSpy).toHaveBeenCalledWith('http://example.com');

        const jsOpen = window.open;
        window.open = jest.fn();

        await send('windowRedirect', {
            url: 'http://example.com',
            newTab: true,
        });

        expect(window.open).toHaveBeenCalledWith('http://example.com', '_blank');
        window.open = jsOpen;
    });

    it('should handle windowRouterPush', async () => {
        Shopware.Application = {
            view: {
                router: {
                    push: jest.fn(),
                },
            },
        };

        await send('windowRouterPush', {
            name: 'sw.product.index',
        });

        expect(Shopware.Application.view.router.push).toHaveBeenCalledWith({
            name: 'sw.product.index',
            params: undefined,
            path: '',
            replace: false,
        });
    });

    it('should handle windowRouterGetPath', async () => {
        Shopware.Application = {
            view: {
                router: {
                    currentRoute: {
                        value: {
                            fullPath: '/products/detail/123',
                        },
                    },
                },
            },
        };

        const result = await send('windowRouterGetPath');

        expect(result).toBe('/products/detail/123');
    });

    it('should return empty string when router is not available for windowRouterGetPath', async () => {
        Shopware.Application = { view: { router: undefined } };

        const result = await send('windowRouterGetPath');

        expect(result).toBe('');
    });
});
