/**
 * @sw-package framework
 */
import { execFileSync, spawnSync } from 'child_process';
import fs from 'fs';
import os from 'os';
import path from 'path';

// The PHP development image and CI provide OpenSSL; other local test environments report these cases as skipped.
const describeWithOpenSsl = spawnSync('openssl', ['version']).status === 0 ? describe : describe.skip;

// Vite 8 is ESM-only; execute the real server outside Jest's module loader.
describe('extension listener integration', () => {
    const administrationRoot = path.resolve(__dirname, '../../../..');
    let temporary: string;

    beforeEach(() => {
        temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'extension-listener-result-'));
    });
    afterEach(() => {
        fs.rmSync(temporary, { recursive: true, force: true });
    });

    function run(scenario: string): Record<string, unknown> {
        const output = path.join(temporary, 'result.json');
        execFileSync(
            process.execPath,
            [
                '-r',
                require.resolve('ts-node/register/transpile-only'),
                path.join(__dirname, 'integration-runner.ts'),
                scenario,
                output,
            ],
            {
                cwd: administrationRoot,
                env: { ...process.env, VITE_MODE: 'development', VITE_CONFIG_NATIVE_IGNORE_WARNING: 'true' },
                timeout: 90000,
                stdio: 'pipe',
            },
        );
        return JSON.parse(fs.readFileSync(output, 'utf8')) as Record<string, unknown>;
    }

    it('isolates conflicting aliases, config hooks, dependency versions and linked peers through HMR and restarts', () => {
        expect(run('isolation')).toMatchObject({ isolated: true, recovered: true, shutdown: true });
    }, 120000);

    it('closes resources from a failed extension startup and retries it on restart', () => {
        expect(run('startup-failure')).toMatchObject({ recovered: true, shutdown: true });
    }, 120000);

    describeWithOpenSsl('certificate-verified TLS', () => {
        it('serves modules and isolated HMR over HTTPS and WSS', () => {
            expect(run('https')).toMatchObject({ secure: true, isolated: true, shutdown: true });
        }, 120000);

        it('serves HMR through a reverse proxy with a different public port', () => {
            expect(run('reverse-proxy')).toMatchObject({ secure: true, isolated: true, shutdown: true });
        }, 120000);
    });

    it('serves bundled extension entries and their HMR clients through one listener', () => {
        expect(run('bundled')).toMatchObject({ listenerCount: 1 });
    }, 120000);
});
