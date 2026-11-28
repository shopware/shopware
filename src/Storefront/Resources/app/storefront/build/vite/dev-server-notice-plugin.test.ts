// @vitest-environment node
import { describe, expect, it, vi } from 'vitest';
import type { ViteDevServer } from 'vite';
import { devServerNoticePlugin } from './dev-server-notice-plugin';
import type { StorefrontDevUpdate } from './storefront-dev-update';

function printNotice(devUpdate?: StorefrontDevUpdate): string {
    const info = vi.fn();
    const server = {
        config: { server: { port: 5175 }, logger: { info } },
        printUrls: () => undefined,
    } as unknown as ViteDevServer;

    const plugin = devServerNoticePlugin(devUpdate);
    (plugin.configureServer as (server: ViteDevServer) => void)(server);
    server.printUrls();

    return info.mock.calls[0]?.[0] as string;
}

describe('devServerNoticePlugin', () => {
    it('names the reload mode by default', () => {
        expect(printNotice()).toContain('Changes reload the page. Set SHOPWARE_STOREFRONT_DEV_UPDATE=hmr to apply them in place.');
    });

    it.each([
        ['hmr', 'Changes are applied to the open page in place'],
        ['manual', 'Changes are not sent to the page, reload it yourself'],
    ] as const)('names the %s mode', (devUpdate, notice) => {
        expect(printNotice(devUpdate)).toContain(notice);
    });
});
