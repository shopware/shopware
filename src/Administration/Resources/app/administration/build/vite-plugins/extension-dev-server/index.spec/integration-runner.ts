/**
 * @sw-package framework
 * @private
 */
import assert from 'assert/strict';
import fs from 'fs/promises';
import path from 'path';
import https from 'https';
import http from 'http';
import net from 'net';
import type { IncomingMessage } from 'http';
import type { Duplex } from 'stream';
import { execFileSync } from 'child_process';
import type { ViteDevServer } from 'vite';
import { availablePort, config, connect, createFixture, graph, nextUpdate, request, until, write } from './fixture';
import type { HmrMessage, TestSocket } from './fixture';

async function main(scenario: string): Promise<Record<string, unknown>> {
    const fixture = await createFixture();
    let parent: ViteDevServer | undefined;
    let proxy: https.Server | undefined;
    let removeProxyUpgrade: (() => void) | undefined;
    const sockets: TestSocket[] = [];
    const listening = new Set<net.Server>();
    // eslint-disable-next-line @typescript-eslint/unbound-method -- restored later and invoked with apply on the real server.
    const originalListen = net.Server.prototype.listen;
    net.Server.prototype.listen = function (this: net.Server, ...args: Parameters<net.Server['listen']>) {
        listening.add(this);
        return originalListen.apply(this, args);
    } as net.Server['listen'];
    const liveServers = () => Array.from(listening).filter((server) => server.listening);

    try {
        if (scenario === 'startup-failure') await config(fixture.extensions[1], 'beta', true);
        const secure = scenario === 'https' || scenario === 'reverse-proxy';
        let certificate: Buffer | undefined;
        let tls: { cert: Buffer; key: Buffer } | undefined;
        if (secure) {
            const key = path.join(fixture.root, 'key.pem');
            const cert = path.join(fixture.root, 'cert.pem');
            execFileSync(
                'openssl',
                [
                    'req',
                    '-x509',
                    '-newkey',
                    'rsa:2048',
                    '-nodes',
                    '-days',
                    '1',
                    '-subj',
                    '/CN=localhost',
                    '-addext',
                    'subjectAltName=DNS:localhost,IP:127.0.0.1',
                    '-keyout',
                    key,
                    '-out',
                    cert,
                ],
                { stdio: 'ignore' },
            );
            tls = { key: await fs.readFile(key), cert: await fs.readFile(cert) };
            certificate = tls.cert;
        }
        const proxyPort = scenario === 'reverse-proxy' ? await availablePort() : undefined;
        const started = await fixture.start({
            https: scenario === 'https' ? tls : undefined,
            ws: proxyPort ? { clientPort: proxyPort, protocol: 'wss' } : undefined,
            bundled: scenario === 'bundled',
        });
        parent = started.parent;
        let origin = started.origin;
        assert.deepEqual(liveServers(), [parent.httpServer], 'Extensions opened an extra TCP listener');

        if (proxyPort) {
            const upstream = new URL(origin);
            proxy = https.createServer(tls!, (incoming, response) => {
                const forwarding = http.request(
                    new URL(incoming.url!, upstream),
                    { method: incoming.method, headers: incoming.headers },
                    (result) => {
                        response.writeHead(result.statusCode ?? 502, result.headers);
                        result.pipe(response);
                    },
                );
                forwarding.once('error', () => {
                    response.writeHead(502);
                    response.end();
                });
                incoming.pipe(forwarding);
            });
            function onUpgrade(incoming: IncomingMessage, socket: Duplex, head: Buffer) {
                const forwarding = net.connect(Number(upstream.port), upstream.hostname, () => {
                    forwarding.write(
                        `${incoming.method} ${incoming.url} HTTP/1.1\r\n${incoming.rawHeaders.reduce((headers, value, index) => headers + (index % 2 ? `${value}\r\n` : `${value}: `), '')}\r\n`,
                    );
                    if (head.length) forwarding.write(head);
                    socket.pipe(forwarding).pipe(socket);
                });
                socket.once('error', () => forwarding.destroy());
                forwarding.once('error', () => socket.destroy());
                socket.once('close', () => forwarding.destroy());
            }
            proxy.on('upgrade', onUpgrade);
            removeProxyUpgrade = () => proxy?.off('upgrade', onUpgrade);
            await new Promise<void>((resolve) => proxy!.listen(proxyPort, '127.0.0.1', resolve));
            origin = `https://127.0.0.1:${proxyPort}`;
        }

        if (scenario === 'bundled') {
            const manifest = JSON.parse(
                await fs.readFile(path.resolve(fixture.admin, '../../sw-plugin-dev.json'), 'utf8'),
            ) as Record<string, { js: string; hmrSrc: string }>;
            for (const name of ['alpha', 'beta']) {
                const entry = await until(
                    () => request(`${origin}${manifest[name].js}`),
                    (result) => result.status === 200 && result.body.includes(`${name}-alias`),
                );
                assert.ok(entry.body.includes(`${name}-alias`), `Bundled entry lost its alias: ${entry.body.slice(-2500)}`);
                assert.ok(entry.body.includes(`${name}-dependency-`), 'Bundled entry lost its dependency');
                assert.equal((await request(`${origin}${manifest[name].hmrSrc}`)).status, 200);
            }
            const socket = await connect(origin, '/_internal_ext/alpha/', undefined, 'bundledDevClient.mjs');
            const control = await connect(origin, '/_internal_ext/beta/', undefined, 'bundledDevClient.mjs');
            sockets.push(socket, control);
            const controlUpdates: HmrMessage[] = [];
            function onControl(event: MessageEvent) {
                controlUpdates.push(JSON.parse(String(event.data)) as HmrMessage);
            }
            control.addEventListener('message', onControl);
            socket.send(
                JSON.stringify({ type: 'custom', event: 'vite:client-connected', data: { clientId: 'alpha-test-client' } }),
            );
            control.send(
                JSON.stringify({ type: 'custom', event: 'vite:client-connected', data: { clientId: 'beta-test-client' } }),
            );
            await request(`${origin}/_internal_ext/alpha/config-probe`);
            const update = nextUpdate(socket, 'bundled-dev-update');
            await write(path.join(fixture.extensions[0].path, 'label.js'), 'export default "alpha-alias-updated";');
            const message = await update;
            assert.ok(message.url, 'Bundled HMR must provide an update module URL');
            const payload = await request(new URL(message.url, `${origin}/_internal_ext/alpha/`).href);
            assert.equal(payload.status, 200);
            assert.ok(payload.body.includes('alpha-alias-updated'));
            await new Promise((resolve) => setTimeout(resolve, 200));
            control.removeEventListener('message', onControl);
            assert.equal(
                controlUpdates.filter((item) => ['bundled-dev-update', 'full-reload'].includes(item.type)).length,
                0,
            );
            return { scenario, listenerCount: liveServers().length };
        }

        if (scenario === 'startup-failure') {
            assert.equal((await request(`${origin}/_internal_ext/beta/main.js`)).status, 503);
            assert.equal((await request(`${origin}/_internal_ext/alpha/config-probe`)).body, 'alpha-hook');
            assert.equal(parent.httpServer!.listenerCount('upgrade'), 2, 'Failed startup leaked an upgrade handler');
            await config(fixture.extensions[1], 'beta');
            await parent.restart();
            assert.equal((await request(`${origin}/_internal_ext/beta/config-probe`)).body, 'beta-hook');
        } else {
            for (const name of ['alpha', 'beta']) {
                const output = await graph(origin, name, certificate);
                for (const marker of [
                    'alias',
                    'virtual',
                    'transform',
                    'config',
                    'dependency',
                ]) {
                    assert.ok(output.includes(`${name}-${marker}`), `${name} lost its ${marker} configuration`);
                }
                assert.ok(
                    !output.includes(`${name === 'alpha' ? 'beta' : 'alpha'}-dependency`),
                    'Dependency versions leaked between extensions',
                );
                assert.equal(
                    (await request(`${origin}/_internal_ext/${name}/config-probe`, certificate)).body,
                    `${name}-hook`,
                );
            }
            assert.equal((await request(`${origin}/_internal_ext/alpha/missing.ts`, certificate)).status, 404);
            assert.equal((await request(`${origin}/main.js`, certificate)).status, 200);

            const [alpha, beta] = await Promise.all([
                connect(origin, '/_internal_ext/alpha/', certificate),
                connect(origin, '/_internal_ext/beta/', certificate),
            ]);
            sockets.push(alpha, beta);
            const betaUpdates: HmrMessage[] = [];
            function collectBeta(event: MessageEvent) {
                betaUpdates.push(JSON.parse(String(event.data)) as HmrMessage);
            }
            beta.addEventListener('message', collectBeta);
            const update = nextUpdate(alpha);
            await write(path.join(fixture.extensions[0].path, 'label.js'), 'export default "alpha-alias-updated";');
            assert.ok((await update).updates?.length);
            await new Promise((resolve) => setTimeout(resolve, 200));
            beta.removeEventListener('message', collectBeta);
            assert.equal(betaUpdates.filter((message) => ['update', 'full-reload'].includes(message.type)).length, 0);

            if (scenario === 'isolation') {
                const transformed = path.join(fixture.extensions[0].path, 'transform.ts');
                await write(transformed, 'export const broken = ;');
                await until(
                    () => request(`${origin}/_internal_ext/alpha/transform.ts`),
                    (response) => response.status === 500,
                );
                assert.equal((await request(`${origin}/_internal_ext/beta/config-probe`)).body, 'beta-hook');
                await write(transformed, 'export default "TRANSFORM_ME";');
                await until(
                    () => request(`${origin}/_internal_ext/alpha/transform.ts`),
                    (response) => response.status === 200 && response.body.includes('alpha-transform'),
                );
                sockets.forEach((socket) => socket.terminate());
                await config(fixture.extensions[0], 'alpha-v2');
                await until(
                    () => request(`${origin}/_internal_ext/alpha/config-probe`),
                    (response) => response.body === 'alpha-v2-hook',
                );
                assert.equal((await request(`${origin}/_internal_ext/beta/config-probe`)).body, 'beta-hook');
                for (let cycle = 0; cycle < 3; cycle += 1) {
                    await parent.restart();
                    assert.equal((await request(`${origin}/_internal_ext/alpha/config-probe`)).body, 'alpha-v2-hook');
                    assert.equal(parent.httpServer!.listenerCount('upgrade'), 3, 'Restart accumulated upgrade handlers');
                    assert.deepEqual(liveServers(), [parent.httpServer]);
                }
            }
        }

        sockets.forEach((socket) => socket.terminate());
        await parent.close();
        parent = undefined;
        assert.equal(liveServers().length, proxy ? 1 : 0, 'Shutdown left a listening server');
        for (const { watcher } of fixture.captured) {
            assert.deepEqual(watcher.getWatched(), {}, 'Shutdown left an extension watcher active');
        }
        return { scenario, isolated: true, recovered: true, secure, shutdown: true };
    } finally {
        sockets.forEach((socket) => socket.terminate());
        await parent?.close();
        await Promise.all(fixture.captured.map(({ server }) => server.close()));
        removeProxyUpgrade?.();
        if (proxy) await new Promise<void>((resolve) => proxy!.close(() => resolve()));
        net.Server.prototype.listen = originalListen;
        await fixture.remove();
    }
}

void main(process.argv[2]).then(
    async (result) => fs.writeFile(process.argv[3], JSON.stringify(result)),
    (error: unknown) => {
        console.error(error);
        process.exitCode = 1;
    },
);
