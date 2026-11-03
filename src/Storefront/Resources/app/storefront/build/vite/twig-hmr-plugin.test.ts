// @vitest-environment node
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { EventEmitter } from 'node:events';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { ViteDevServer } from 'vite';
import { twigHmrPlugin } from './twig-hmr-plugin';

type MockServer = ViteDevServer & {
    config: { logger: { info: ReturnType<typeof vi.fn> } };
    watcher: EventEmitter & { add: ReturnType<typeof vi.fn> };
    ws: { send: ReturnType<typeof vi.fn> };
};

function createMockServer(): MockServer {
    const watcher = new EventEmitter() as EventEmitter & { add: ReturnType<typeof vi.fn> };
    watcher.add = vi.fn();

    return {
        config: { logger: { info: vi.fn() } },
        watcher,
        ws: { send: vi.fn() },
    } as unknown as MockServer;
}

describe('build/vite/twig-hmr-plugin', () => {
    let projectRoot: string;
    let viewRoot: string;

    function startPlugin(): MockServer {
        const plugin = twigHmrPlugin(projectRoot);
        const server = createMockServer();
        (plugin.configureServer as (server: ViteDevServer) => void)(server);

        return server;
    }

    beforeEach(() => {
        vi.useFakeTimers();
        projectRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'sw-twig-hmr-plugin-'));
        viewRoot = path.join(projectRoot, 'src/Storefront/Resources/views');
        fs.mkdirSync(path.join(viewRoot, 'storefront'), { recursive: true });
        fs.mkdirSync(path.join(projectRoot, 'var'), { recursive: true });
        fs.writeFileSync(path.join(projectRoot, 'var/plugins.json'), JSON.stringify({
            Storefront: { technicalName: 'storefront', basePath: 'src/Storefront/', views: ['Resources/views'] },
        }));
    });

    afterEach(() => {
        vi.useRealTimers();
        fs.rmSync(projectRoot, { recursive: true, force: true });
    });

    it('watches the Storefront view directory, because Vite disables globs in its watcher', () => {
        const server = startPlugin();

        expect(server.watcher.add).toHaveBeenCalledWith([viewRoot]);
    });

    it('sends the Twig changes of the debounce window as one event, relative to the project root', () => {
        const server = startPlugin();

        server.watcher.emit('all', 'change', path.join(viewRoot, 'storefront/base.html.twig'));
        server.watcher.emit('all', 'add', path.join(viewRoot, 'storefront/new.html.twig'));
        vi.advanceTimersByTime(50);

        expect(server.ws.send).toHaveBeenCalledTimes(1);
        expect(server.ws.send).toHaveBeenCalledWith('sw:twig-update', {
            files: [
                'src/Storefront/Resources/views/storefront/base.html.twig',
                'src/Storefront/Resources/views/storefront/new.html.twig',
            ],
            structural: true,
        });
    });

    it('ignores files outside the views and files that are no templates', () => {
        const server = startPlugin();

        server.watcher.emit('all', 'change', path.join(projectRoot, 'other/page.html.twig'));
        server.watcher.emit('all', 'change', path.join(viewRoot, 'storefront/style.scss'));
        server.watcher.emit('all', 'addDir', path.join(viewRoot, 'storefront/component.twig'));
        vi.advanceTimersByTime(50);

        expect(server.ws.send).not.toHaveBeenCalled();
    });

    it('does nothing without var/plugins.json', () => {
        fs.rmSync(path.join(projectRoot, 'var/plugins.json'));

        const server = startPlugin();

        expect(server.watcher.add).not.toHaveBeenCalled();
    });

    it('keeps Vite from handling Twig changes on its own', () => {
        const hotUpdate = twigHmrPlugin(projectRoot).hotUpdate as (options: { file: string }) => unknown;

        expect(hotUpdate({ file: path.join(viewRoot, 'storefront/base.html.twig') })).toEqual([]);
        expect(hotUpdate({ file: '/project/src/main.js' })).toBeUndefined();
    });
});
