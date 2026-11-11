/**
 * @sw-package framework
 */
import { reloadForTwigUpdate, startTwigReload } from 'src/dev/twig-hmr/twig-reload';

function createEnv() {
    return {
        fetch: jest.fn(() => Promise.resolve({ ok: true })),
        reload: jest.fn(),
    };
}

describe('dev/twig-hmr/twig-reload', () => {
    it('reloads the page right away for a changed template', async () => {
        const env = createEnv();

        await reloadForTwigUpdate({ files: ['a.html.twig'], structural: false }, env);

        expect(env.fetch).not.toHaveBeenCalled();
        expect(env.reload).toHaveBeenCalledTimes(1);
    });

    it('resets the compiled templates before it reloads the page for an added or removed template', async () => {
        const env = createEnv();
        env.fetch.mockImplementation(() => {
            expect(env.reload).not.toHaveBeenCalled();

            return Promise.resolve({ ok: true });
        });

        await reloadForTwigUpdate({ files: ['a.html.twig'], structural: true }, env);

        expect(env.fetch).toHaveBeenCalledWith(window.location.href, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'text/html',
                'sw-twig-hmr': '1',
                'sw-twig-hmr-reset': '1',
            },
        });
        expect(env.reload).toHaveBeenCalledTimes(1);
    });

    it('reloads the page even when the reset request fails', async () => {
        const env = createEnv();
        env.fetch.mockRejectedValue(new Error('offline'));
        jest.spyOn(console, 'warn').mockImplementation(() => {});

        await reloadForTwigUpdate({ files: [], structural: true }, env);

        expect(console.warn).toHaveBeenCalledWith(expect.stringContaining('Could not reset'), expect.any(Error));
        expect(env.reload).toHaveBeenCalledTimes(1);
    });

    it('reloads the page once for updates that arrive while it reloads', async () => {
        const env = createEnv();
        let onUpdate;

        startTwigReload((handler) => {
            onUpdate = handler;
        }, env);
        onUpdate({ files: ['a.html.twig'], structural: true });
        onUpdate(undefined);
        await Promise.resolve();
        await Promise.resolve();

        expect(env.fetch).toHaveBeenCalledTimes(1);
        expect(env.reload).toHaveBeenCalledTimes(1);
    });
});
