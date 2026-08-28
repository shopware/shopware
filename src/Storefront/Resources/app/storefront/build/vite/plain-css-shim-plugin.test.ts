// @vitest-environment node
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { plainCssShimPlugin } from './plain-css-shim-plugin';

function resolveHook<T extends (...args: never[]) => unknown>(
    hook: T | { handler: T } | undefined,
): T | undefined {
    if (typeof hook === 'function') {
        return hook;
    }

    return hook?.handler;
}

describe('plainCssShimPlugin', () => {
    let fixtureRoot: string;

    beforeAll(() => {
        fixtureRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'plain-css-shim-plugin-'));
    });

    afterAll(() => {
        fs.rmSync(fixtureRoot, { recursive: true, force: true });
    });

    it('resolves and loads virtual css shim entries', () => {
        const absCssPath = path.join(fixtureRoot, 'component.css');
        fs.writeFileSync(absCssPath, '.class { color: red; }');

        const shimId = '\0sw-plain-css:Component/Test.css';
        const plugin = plainCssShimPlugin(new Map([[shimId, absCssPath]]));
        const resolveId = resolveHook(plugin.resolveId) as ((...args: unknown[]) => unknown) | undefined;
        const load = resolveHook(plugin.load) as ((...args: unknown[]) => unknown) | undefined;

        expect(resolveId?.call({}, shimId, undefined, { isEntry: false })).toBe(shimId);
        expect(resolveId?.call({}, 'not-a-shim', undefined, { isEntry: false })).toBeNull();
        expect(load?.call({}, shimId, { ssr: false })).toContain('color: red');
        expect(load?.call({}, 'unknown-shim', { ssr: false })).toBeNull();
    });
});
