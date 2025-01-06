/**
 * @package admin
 */

import initializeWindow from 'src/app/init/window.init';
import { send } from '@shopware-ag/meteor-admin-sdk/es/channel';

describe('src/app/init/window.init.ts', () => {
    const reload = window.location.reload;

    beforeAll(() => {
        initializeWindow();
        Object.defineProperty(window, 'location', {
            value: { reload: jest.fn() },
        });
        window.open = jest.fn();
    });

    afterEach(() => {
        jest.clearAllMocks();
        window.location.reload = reload;
    });

    it('should handle windowReload', async () => {
        await send('windowReload');

        expect(window.location.reload).toHaveBeenCalled();
    });

    it('should handle windowRedirect', async () => {
        await send('windowRedirect', {
            url: 'http://example.com',
            newTab: false,
        });

        expect(window.location.href).toBe('http://example.com');

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
});
