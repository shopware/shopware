/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as os from 'node:os';
import * as path from 'node:path';
import * as vm from 'node:vm';
import { execFileSync } from 'node:child_process';
import { productDetailFixture } from '../app/__fixtures__/compare-fixture';
import { STATIC_REPORT_GLOBAL, type StaticReportData } from './types';

const fixture = productDetailFixture();
const DATA: StaticReportData = {
    base: fixture.base,
    head: fixture.head,
    diff: fixture.diff,
    worktreeCommit: '1a2b3c4d5e6f708192a3b4c5d6e7f8091a2b3c4d',
    generatedAt: '2026-10-21T08:30:00.000Z',
};

function waitFor(condition: () => boolean, timeoutMs = 5000): Promise<void> {
    const started = Date.now();

    return new Promise((resolve, reject) => {
        const check = (): void => {
            if (condition()) {
                resolve();
            } else if (Date.now() - started > timeoutMs) {
                reject(new Error('Timed out'));
            } else {
                setTimeout(check, 20);
            }
        };

        check();
    });
}

describe('api-explorer/report/static-site', () => {
    let outDir: string;
    const read = (file: string): string => fs.readFileSync(path.join(outDir, file), 'utf8');

    beforeAll(() => {
        outDir = fs.mkdtempSync(path.join(os.tmpdir(), 'api-explorer-static-'));
        const dataFile = path.join(outDir, 'input.json');
        fs.writeFileSync(dataFile, JSON.stringify(DATA));

        // Vite and esbuild do not run inside the jsdom test environment.
        execFileSync(
            process.execPath,
            [
                '-r',
                'ts-node/register/transpile-only',
                '-e',
                `require(${JSON.stringify(path.join(__dirname, 'static-site'))})
                    .writeStaticSite(${JSON.stringify(outDir)}, require(${JSON.stringify(dataFile)}))
                    .catch((error) => { console.error(error); process.exit(1); });`,
            ],
            { cwd: path.resolve(__dirname, '..', '..', '..'), stdio: 'pipe' },
        );
        fs.rmSync(dataFile);
    }, 180_000);

    afterAll(() => {
        fs.rmSync(outDir, { recursive: true, force: true });
    });

    it('writes an index.html that loads classic scripts only', () => {
        const html = read('index.html');

        expect(html).not.toContain('type="module"');
        expect(html.indexOf('<script src="./data.js"></script>')).toBeLessThan(
            html.indexOf('<script src="./app.js"></script>'),
        );
        expect(html).toContain('<link rel="stylesheet" href="./app.css">');
        expect(fs.existsSync(path.join(outDir, 'app.css'))).toBe(true);
        expect(read('app.js')).not.toMatch(/\bimport\s*\(|^\s*(import|export)\s/m);
    });

    it('writes a data.js that defines the report global', () => {
        const context: { window: Record<string, unknown> } = { window: {} };
        vm.runInNewContext(read('data.js'), context);

        expect(context.window[STATIC_REPORT_GLOBAL]).toEqual(DATA);
    });

    it('mounts the bundle against the embedded data without requests', async () => {
        const originalFetch = global.fetch;
        const fetch = jest.fn();
        global.fetch = fetch;
        jest.spyOn(window, 'scrollTo').mockImplementation(() => undefined);
        window.location.hash = '#/components/sw-product-detail';
        document.body.innerHTML = '<div id="app"></div>';

        const run = (file: string): void => {
            const script = document.createElement('script');
            script.textContent = read(file);
            document.body.appendChild(script);
        };
        run('data.js');
        run('app.js');
        await waitFor(() => document.querySelector('h1') !== null);

        const text = document.querySelector('.app')?.textContent ?? '';
        expect(document.querySelector('h1')?.textContent).toBe('sw-product-detail');
        expect(text).toContain('Static report, generated 21 Oct 2026');
        expect(text).toContain('Checked-out commit @ 1a2b3c4');
        expect(text).not.toContain('Local state');
        expect(text).not.toContain('Refresh');
        expect(document.querySelector('button[aria-label="Swap base and comparison"]')).toBeNull();
        expect(document.querySelector('a[href^="#/components"]')?.getAttribute('href')).not.toContain('base=');

        window.location.hash = '#/shopware?path=Shopware.Component';
        await waitFor(() => document.querySelector('h1')?.textContent !== 'sw-product-detail');

        expect(window.location.hash).toBe('#/shopware?path=Shopware.Component');
        expect(fetch).not.toHaveBeenCalled();

        global.fetch = originalFetch;
        window.location.hash = '';
        delete (window as unknown as Record<string, unknown>)[STATIC_REPORT_GLOBAL];
    });
});
