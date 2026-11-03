// @vitest-environment node
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { STOREFRONT_DEV_UPDATE_ENV, resolveStorefrontDevUpdate } from './storefront-dev-update';

describe('resolveStorefrontDevUpdate', () => {
    let projectRoot: string;

    beforeEach(() => {
        projectRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'storefront-dev-update-'));
        vi.stubEnv(STOREFRONT_DEV_UPDATE_ENV, undefined);
    });

    afterEach(() => {
        vi.unstubAllEnvs();
        fs.rmSync(projectRoot, { recursive: true, force: true });
    });

    it('reloads the page when nothing is set', () => {
        expect(resolveStorefrontDevUpdate(projectRoot, 'development')).toBe('reload');
    });

    it.each(['reload', 'hmr', 'manual'] as const)('accepts %s from the shell', (value) => {
        vi.stubEnv(STOREFRONT_DEV_UPDATE_ENV, value);

        expect(resolveStorefrontDevUpdate(projectRoot, 'development')).toBe(value);
    });

    it('reads the value from the .env.local of the project root', () => {
        fs.writeFileSync(path.join(projectRoot, '.env'), `${STOREFRONT_DEV_UPDATE_ENV}=manual\n`);
        fs.writeFileSync(path.join(projectRoot, '.env.local'), `${STOREFRONT_DEV_UPDATE_ENV}=hmr\n`);

        expect(resolveStorefrontDevUpdate(projectRoot, 'development')).toBe('hmr');
    });

    it('prefers the shell over the .env files', () => {
        fs.writeFileSync(path.join(projectRoot, '.env.local'), `${STOREFRONT_DEV_UPDATE_ENV}=hmr\n`);
        vi.stubEnv(STOREFRONT_DEV_UPDATE_ENV, 'manual');

        expect(resolveStorefrontDevUpdate(projectRoot, 'development')).toBe('manual');
    });

    it('warns and reloads the page for an unknown value', () => {
        vi.stubEnv(STOREFRONT_DEV_UPDATE_ENV, '1');
        const warn = vi.fn();

        expect(resolveStorefrontDevUpdate(projectRoot, 'development', warn)).toBe('reload');
        expect(warn).toHaveBeenCalledWith(expect.stringContaining('Unknown SHOPWARE_STOREFRONT_DEV_UPDATE value "1"'));
    });
});
