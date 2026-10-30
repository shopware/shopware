jest.mock('chokidar', () => ({ watch: jest.fn() }));

const chokidar = require('chokidar');
const setupTwigHmrWatcher = require('../../../build/webpack/twig-hmr-watcher');

/**
 * @sw-package framework
 */
describe('build/webpack/twig-hmr-watcher', () => {
    let emit;
    let devServer;

    beforeEach(() => {
        jest.useFakeTimers();
        jest.spyOn(console, 'log').mockImplementation(() => {});

        chokidar.watch.mockImplementation(() => {
            const watcher = {
                on: jest.fn((name, handler) => {
                    emit = handler;

                    return watcher;
                }),
            };

            return watcher;
        });

        devServer = {
            webSocketServer: { clients: ['client'] },
            sendMessage: jest.fn(),
        };
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    test('watches the given paths relative to the project root', () => {
        setupTwigHmrWatcher(devServer, ['src/Storefront/Resources/views/**/*.twig'], '/project');

        expect(chokidar.watch).toHaveBeenCalledWith(
            ['src/Storefront/Resources/views/**/*.twig'],
            expect.objectContaining({ cwd: '/project', ignoreInitial: true }),
        );
    });

    test('sends the changes of the debounce window as one message', () => {
        setupTwigHmrWatcher(devServer, [], '/project');

        emit('change', 'a.html.twig');
        emit('add', 'b.html.twig');
        emit('change', 'a.html.twig');
        jest.advanceTimersByTime(49);
        emit('unlink', 'c.html.twig');
        expect(devServer.sendMessage).not.toHaveBeenCalled();

        jest.advanceTimersByTime(50);
        expect(devServer.sendMessage).toHaveBeenCalledTimes(1);
        expect(devServer.sendMessage).toHaveBeenCalledWith(['client'], 'sw-twig-update', {
            files: ['a.html.twig', 'b.html.twig', 'c.html.twig'],
            structural: true,
        });

        emit('change', 'd.html.twig');
        jest.advanceTimersByTime(50);
        expect(devServer.sendMessage).toHaveBeenLastCalledWith(['client'], 'sw-twig-update', {
            files: ['d.html.twig'],
            structural: false,
        });
    });

    test.each(['add', 'unlink'])('marks a window with an %s event as structural', (event) => {
        setupTwigHmrWatcher(devServer, [], '/project');

        emit('change', 'a.html.twig');
        emit(event, 'b.html.twig');
        jest.advanceTimersByTime(50);

        expect(devServer.sendMessage).toHaveBeenLastCalledWith(['client'], 'sw-twig-update', {
            files: ['a.html.twig', 'b.html.twig'],
            structural: true,
        });
    });

    test('ignores directory events', () => {
        setupTwigHmrWatcher(devServer, [], '/project');

        emit('addDir', 'component');
        emit('unlinkDir', 'component');
        jest.advanceTimersByTime(50);

        expect(devServer.sendMessage).not.toHaveBeenCalled();
    });

    test('does not send before the web socket server is running', () => {
        devServer.webSocketServer = null;
        setupTwigHmrWatcher(devServer, [], '/project');

        emit('change', 'a.html.twig');
        jest.advanceTimersByTime(50);

        expect(devServer.sendMessage).not.toHaveBeenCalled();
    });
});
