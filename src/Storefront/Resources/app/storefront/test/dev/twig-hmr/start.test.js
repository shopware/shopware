/**
 * @sw-package framework
 */
import { init, refresh } from 'src/dev/twig-hmr/twig-hmr';
import { start } from 'src/dev/twig-hmr/start';

jest.mock('src/dev/twig-hmr/twig-hmr', () => ({
    init: jest.fn(),
    refresh: jest.fn(() => Promise.resolve()),
    LOG_PREFIX: '[sw-twig-hmr]',
}));

describe('dev/twig-hmr/start', () => {
    beforeEach(() => {
        jest.spyOn(console, 'info').mockImplementation(() => {});
    });

    afterEach(() => {
        delete window.swTwigHmr;
    });

    it('initializes before it subscribes and refreshes the page for every update', () => {
        let onUpdate;
        const subscribe = jest.fn((handler) => {
            expect(init).toHaveBeenCalledTimes(1);
            onUpdate = handler;
        });

        start(subscribe);
        onUpdate({ files: ['a.html.twig'], structural: true });
        onUpdate(undefined);

        expect(window.swTwigHmr.refresh).toBe(refresh);
        expect(refresh.mock.calls).toEqual([
            [['a.html.twig'], { structural: true }],
            [[], { structural: false }],
        ]);
    });
});
