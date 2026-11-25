/**
 * @sw-package framework
 */
describe('dev/twig-hmr/twig-hmr.client', () => {
    let sockets;
    let refresh;
    let init;

    class WebSocketMock {
        constructor(url) {
            this.url = url;
            this.listeners = {};
            sockets.push(this);
        }

        addEventListener(type, listener) {
            this.listeners[type] = listener;
        }

        emit(type, event = {}) {
            this.listeners[type](event);
        }
    }

    function loadClient() {
        jest.isolateModules(() => {
            jest.doMock('src/dev/twig-hmr/twig-hmr', () => ({ init, refresh, LOG_PREFIX: '[sw-twig-hmr]' }));
            require('src/dev/twig-hmr/twig-hmr.client');
        });
    }

    beforeEach(() => {
        sockets = [];
        refresh = jest.fn(() => Promise.resolve());
        init = jest.fn();
        global.WebSocket = WebSocketMock;
        jest.spyOn(console, 'info').mockImplementation(() => {});
        document.head.innerHTML = '';
    });

    afterEach(() => {
        jest.useRealTimers();
        delete window.swTwigHmr;
    });

    it('connects to the web socket of the dev server that serves the hot reloading script', () => {
        document.head.innerHTML = '<script src="https://shop.test:9999/storefront/hot-reloading.js"></script>';

        loadClient();

        expect(sockets).toHaveLength(1);
        expect(sockets[0].url).toBe('wss://shop.test:9999/ws');
        expect(window.swTwigHmr.refresh).toBe(refresh);
        expect(init).toHaveBeenCalledTimes(1);
    });

    it('connects to the page origin without the hot reloading script', () => {
        loadClient();

        expect(sockets[0].url).toBe('ws://localhost/ws');
    });

    it('refreshes the page for Twig update messages only', () => {
        loadClient();
        const [socket] = sockets;

        socket.emit('message', { data: JSON.stringify({ type: 'sw-twig-update', data: { files: ['a.html.twig'] } }) });
        socket.emit('message', { data: JSON.stringify({ type: 'sw-twig-update', data: { files: ['b.html.twig'], structural: true } }) });
        socket.emit('message', { data: JSON.stringify({ type: 'sw-twig-update' }) });
        socket.emit('message', { data: JSON.stringify({ type: 'hash', data: 'abc' }) });
        socket.emit('message', { data: 'not json' });

        expect(refresh.mock.calls).toEqual([
            [['a.html.twig'], { structural: false }],
            [['b.html.twig'], { structural: true }],
            [[], { structural: false }],
        ]);
    });

    it('reconnects after the dev server closed the connection', () => {
        jest.useFakeTimers();
        loadClient();

        sockets[0].emit('close');
        expect(sockets).toHaveLength(1);

        jest.advanceTimersByTime(1000);
        expect(sockets).toHaveLength(2);
    });
});
