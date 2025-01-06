/**
 * @package admin
 */
import AssetPlugin from './index'

describe('build/vite-plugins/asset-plugin', () => {
    it('should be a function with 2 arguments', () => {
        expect(typeof AssetPlugin).toBe('function');

        // check that the function has 2 arguments
        expect(AssetPlugin.length).toBe(2);
    })

    it('should return dev plugin', () => {
        const plugin = AssetPlugin(false, 'build');

        // Identify plugin by name
        expect(plugin).toHaveProperty('name');
        expect(plugin.name).toBe('shopware-serve-multiple-static');

        // Check if the plugin has a configureServer method
        expect(plugin).toHaveProperty('configureServer');

        const useMock = jest.fn();
        useMock.mockImplementation((fn) => {
            // check that server.middlewares.use calls next() when req.originalUrl is falsy
            let req = { originalUrl: '' };
            let res = {};
            let next = jest.fn();
            fn(req, res, next);
            expect(next).toHaveBeenCalled();

            // check next is called for none matching URL
            req = { originalUrl: '/foo' };
            res = {};
            next = jest.fn();
            fn(req, res, next);
            expect(next).toHaveBeenCalled();

            // check 404 is returned when file does not exist
            console.error = jest.fn();
            req = { originalUrl: '/static' };
            res = {
                writeHead: jest.fn(),
                end: jest.fn(),
            };
            next = jest.fn();
            fn(req, res, next);
            expect(res.writeHead).toHaveBeenCalledWith(404);
            expect(res.end).toHaveBeenCalledWith('Not found');
            expect(console.error).toHaveBeenCalled();
        });

        // Call the configureServer method with a mock server object and check that server.middlewares.use is called
        const server = { middlewares: { use: useMock } };
        plugin.configureServer(server);
        expect(useMock).toHaveBeenCalled();
    })

    it('should return build plugin', async () => {
        const plugin = AssetPlugin(true, 'build');

        // Identify plugin by name
        expect(plugin).toHaveProperty('name');
        expect(plugin.name).toBe('shopware-copy-static-assets');

        // Check if the plugin has a closeBundle method
        expect(plugin).toHaveProperty('closeBundle');
        expect(typeof plugin.closeBundle).toBe('function');
    })
});
