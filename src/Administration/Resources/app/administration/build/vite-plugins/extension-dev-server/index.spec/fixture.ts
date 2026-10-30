/**
 * @sw-package framework
 * @private
 */
import assert from 'assert/strict';
import fs from 'fs/promises';
import path from 'path';
import os from 'os';
import { createServer as createTcpServer } from 'net';
import type { AddressInfo } from 'net';
import http from 'http';
import https from 'https';
import { createRequire } from 'module';
import type { InlineConfig, UserConfig, ViteDevServer } from 'vite';
import ExtensionDevServerPlugin from '../index';
import { loadViteToolchain } from '../../vite-toolchain';
import type { ExtensionDefinition } from '../../utils';

/** @private */
export type CapturedServer = { label: string; server: ViteDevServer; watcher: ViteDevServer['watcher'] };
/** @private */
export type HmrMessage = { type: string; url?: string; updates?: Array<{ path: string; acceptedPath: string }> };
/** @private */
export type TestSocket = WebSocket & { terminate(): void };

const Socket = createRequire(__filename)('ws') as new (
    url: string,
    protocol: string,
    options: { ca?: Buffer; origin: string },
) => TestSocket;

/** @private */
export async function write(file: string, code: string): Promise<void> {
    await fs.mkdir(path.dirname(file), { recursive: true });
    await fs.writeFile(file, code);
}

/** @private */
export async function availablePort(): Promise<number> {
    const server = createTcpServer();
    await new Promise<void>((resolve) => server.listen(0, '127.0.0.1', resolve));
    const port = (server.address() as AddressInfo).port;
    await new Promise<void>((resolve, reject) => server.close((error) => (error ? reject(error) : resolve())));
    return port;
}

/** @private */
export async function until<T>(read: () => Promise<T>, matches: (value: T) => boolean): Promise<T> {
    const deadline = Date.now() + 15000;
    let last: T | undefined;
    while (Date.now() < deadline) {
        try {
            last = await read();
            if (matches(last)) return last;
        } catch {
            // Requests can race Vite's config restart or dependency optimizer.
        }
        await new Promise((resolve) => setTimeout(resolve, 100));
    }
    throw new Error(`Condition timed out; last result: ${JSON.stringify(last).slice(0, 500)}`);
}

/** @private */
export function request(url: string, ca?: Buffer): Promise<{ status: number; body: string }> {
    const target = new URL(url);
    if (target.hostname !== '127.0.0.1' || !['http:', 'https:'].includes(target.protocol)) {
        return Promise.reject(new Error('Fixture requests must target a loopback HTTP server.'));
    }
    return new Promise((resolve, reject) => {
        const client = target.protocol === 'https:' ? https : http;
        const req = client.get(
            { hostname: '127.0.0.1', port: target.port, path: target.pathname + target.search, ca, agent: false },
            (response) => {
                let body = '';
                response.setEncoding('utf8');
                function onData(chunk: string) {
                    body += chunk;
                }
                response.on('data', onData);
                response.once('end', () => {
                    response.off('data', onData);
                    resolve({ status: response.statusCode ?? 0, body });
                });
            },
        );
        req.once('error', reject);
        req.setTimeout(5000, () => req.destroy(new Error(`Request timed out: ${url}`)));
    });
}

/** @private */
export async function connect(origin: string, base: string, ca?: Buffer, clientFile = '@vite/client'): Promise<TestSocket> {
    const client = await request(`${origin}${base}${clientFile}`, ca);
    assert.equal(client.status, 200);
    const token = client.body.match(/const wsToken = "([^"]+)"/)?.[1];
    assert.ok(token, 'Vite must supply a WebSocket token');
    const socket = new Socket(`${origin.replace(/^http/, 'ws')}${base}?token=${token}`, 'vite-hmr', { ca, origin });
    await new Promise<void>((resolve, reject) => {
        const timeout = setTimeout(() => reject(new Error('WebSocket connection timed out')), 5000);
        socket.addEventListener(
            'open',
            () => {
                clearTimeout(timeout);
                resolve();
            },
            { once: true },
        );
        socket.addEventListener(
            'error',
            () => {
                clearTimeout(timeout);
                reject(new Error('WebSocket failed'));
            },
            { once: true },
        );
    });
    return socket;
}

/** @private */
export function nextUpdate(socket: TestSocket, kind = 'update'): Promise<HmrMessage> {
    return new Promise((resolve, reject) => {
        const timeout = setTimeout(() => {
            socket.removeEventListener('message', onMessage);
            reject(new Error('HMR update timed out'));
        }, 10000);
        function onMessage(event: MessageEvent) {
            const message = JSON.parse(String(event.data)) as HmrMessage;
            if (message.type === kind) {
                clearTimeout(timeout);
                socket.removeEventListener('message', onMessage);
                resolve(message);
            }
        }
        socket.addEventListener('message', onMessage);
    });
}

/** @private */
export async function config(extension: ExtensionDefinition, label: string, fail = false): Promise<void> {
    const alias = path.join(extension.path, 'label.js');
    await write(
        path.join(extension.path, 'vite.config.mjs'),
        `
export default {
    resolve: { alias: { '@same': ${JSON.stringify(alias)} } },
    plugins: [{
        name: 'fixture-${extension.technicalName}',
        config() { return { define: { __CONFIG_MARKER__: ${JSON.stringify(JSON.stringify(`${label}-config`))} } }; },
        resolveId(id) { if (id === 'virtual:fixture') return '\\0virtual:fixture'; },
        load(id) { if (id === '\\0virtual:fixture') return ${JSON.stringify(`export default ${JSON.stringify(`${label}-virtual`)};`)}; },
        transform(code, id) { if (id.endsWith('/transform.ts')) return code.replace('TRANSFORM_ME', ${JSON.stringify(`${label}-transform`)}); },
        configureServer(server) {
            globalThis.__extensionTestServers.push({ label: ${JSON.stringify(label)}, server, watcher: server.watcher });
            ${fail ? 'throw new Error("Intentional extension startup failure");' : ''}
            server.middlewares.use((req, res, next) => {
                if (req.url.endsWith('/config-probe')) { res.end(${JSON.stringify(`${label}-hook`)}); return; }
                next();
            });
        }
    }]
};
`,
    );
}

/** @private */
export async function createFixture() {
    const root = await fs.mkdtemp(path.join(os.tmpdir(), 'extension-listener-'));
    const admin = path.join(root, 'src/Administration/Resources/app/administration');
    const captured: CapturedServer[] = [];
    (globalThis as typeof globalThis & { __extensionTestServers: CapturedServer[] }).__extensionTestServers = captured;
    await write(path.join(root, 'var/config_js_features.json'), JSON.stringify({ ADMIN_VITE_8: true }));
    await write(path.join(root, 'package.json'), JSON.stringify({ private: true, workspaces: ['packages/*'] }));
    await write(path.join(admin, 'index.html'), '<div id="app"></div><script type="module" src="./main.js"></script>');
    await write(path.join(admin, 'main.js'), 'export const host = "host-module";');
    const extensions: ExtensionDefinition[] = [];
    for (const [name, version] of [
        ['alpha', '1.0.0'],
        ['beta', '2.0.0'],
    ]) {
        const basePath = path.join(root, 'custom/plugins', name, 'src');
        const extension: ExtensionDefinition = {
            name,
            technicalName: name,
            technicalFolderName: name,
            basePath,
            path: path.join(basePath, 'Resources/app/administration/src'),
            filePath: path.join(basePath, 'Resources/app/administration/src/main.js'),
            isPlugin: true,
            isApp: false,
        };
        const dependency = path.join(root, 'node_modules/.pnpm', `collision-dep@${version}`, 'node_modules/collision-dep');
        await write(
            path.join(dependency, 'package.json'),
            JSON.stringify({ name: 'collision-dep', version, type: 'module', main: './index.js' }),
        );
        await write(path.join(dependency, 'index.js'), `export default '${name}-dependency-${version}';`);
        await write(path.join(extension.path, 'label.js'), `export default '${name}-alias';`);
        await write(path.join(extension.path, 'transform.ts'), 'export default "TRANSFORM_ME";');
        await write(
            extension.filePath,
            `
import label from '@same';
import virtual from 'virtual:fixture';
import transformed from './transform.ts';
import dependency from 'collision-dep';
import linked from 'linked-peer';
export const result = [label, virtual, transformed, dependency, linked, __CONFIG_MARKER__];
if (import.meta.hot) import.meta.hot.accept();
`,
        );
        const linked = path.join(root, 'packages/linked-peer');
        await write(
            path.join(linked, 'package.json'),
            JSON.stringify({
                name: 'linked-peer',
                type: 'module',
                main: './index.js',
                peerDependencies: { 'collision-dep': '*' },
            }),
        );
        await write(path.join(linked, 'index.js'), 'export { default } from "collision-dep";');
        const modules = path.join(extension.path, 'node_modules');
        await fs.mkdir(modules, { recursive: true });
        const linkType = process.platform === 'win32' ? 'junction' : 'dir';
        await fs.symlink(dependency, path.join(modules, 'collision-dep'), linkType);
        await fs.symlink(linked, path.join(modules, 'linked-peer'), linkType);
        await config(extension, name);
        extensions.push(extension);
    }
    process.env.PROJECT_ROOT = root;
    const toolchain = await loadViteToolchain();
    return {
        root,
        admin,
        extensions,
        captured,
        async start(
            options: {
                https?: { cert: Buffer; key: Buffer };
                ws?: { clientPort: number; protocol: string };
                bundled?: boolean;
            } = {},
        ) {
            const port = await availablePort();
            const parentConfig: InlineConfig = {
                root: admin,
                configFile: false,
                logLevel: 'silent',
                server: {
                    host: '127.0.0.1',
                    port,
                    strictPort: true,
                    https: options.https,
                    ...(options.ws ? { ws: options.ws } : {}),
                } as UserConfig['server'],
                optimizeDeps: { noDiscovery: true, include: [] },
                plugins: [ExtensionDevServerPlugin(toolchain, extensions, options.bundled ?? false)],
                ...(options.bundled ? { experimental: { bundledDev: true } as UserConfig['experimental'] } : {}),
                build: { rollupOptions: { input: path.join(admin, 'index.html') } },
            };
            const parent = await toolchain.vite.createServer(parentConfig);
            await parent.listen();
            return { parent, origin: `${options.https ? 'https' : 'http'}://127.0.0.1:${port}` };
        },
        async remove() {
            await fs.rm(root, { recursive: true, force: true });
        },
    };
}

/** @private */
export async function graph(origin: string, name: string, ca?: Buffer): Promise<string> {
    const base = `/_internal_ext/${name}/`;
    return until(
        async () => {
            const pending = [`${origin}${base}main.js`];
            const visited = new Set<string>();
            let result = '';
            while (pending.length) {
                const url = pending.pop()!;
                if (visited.has(url)) continue;
                visited.add(url);
                const response = await request(url, ca);
                assert.equal(response.status, 200, `${url}: ${response.status}`);
                result += response.body;
                for (const match of response.body.matchAll(
                    /^\s*(?:import|export)\s*(?:[^'";]*?\bfrom\s*)?['"]([^'"]+)['"]/gm,
                )) {
                    const imported = new URL(match[1], url);
                    if (imported.pathname.startsWith(base)) pending.push(imported.href);
                }
                assert.ok(visited.size < 100, 'Unexpected fixture module graph');
            }
            return result;
        },
        (result) => result.includes(`${name}-dependency-`) && result.includes(`${name}-virtual`),
    );
}
