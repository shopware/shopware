/**
 * @sw-package framework
 */
import fs from 'node:fs';
import path from 'node:path';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import composables from './index';

jest.unmock('./index');

const MIXIN_REPLACEMENT = /stableVersion:v6\.9\.0 feature:ADMIN_MIXIN_COMPOSABLES/;

/**
 * The default export name of every composable file tagged as a mixin replacement, whether it defines
 * the composable or re-exports a library one, e.g. `export { useI18n as default } from 'vue-i18n'`.
 */
function taggedComposables(): string[] {
    return fs
        .readdirSync(__dirname)
        .filter((file) => /^use-.*\.ts$/.test(file) && !file.endsWith('.spec.ts'))
        .map((file) => fs.readFileSync(path.join(__dirname, file), 'utf8'))
        .filter((source) => MIXIN_REPLACEMENT.test(source))
        .map((source) => /export (?:default function (\w+)|\{ (\w+) as default \})/.exec(source)?.slice(1).find(Boolean))
        .filter((name): name is string => name !== undefined);
}

describe('src/app/composables/index', () => {
    it('publishes exactly the composables tagged as mixin replacements', () => {
        expect(Object.keys(composables).sort()).toEqual(taggedComposables().sort());
    });

    it('publishes the very vue-i18n and vue-router composables the app installs its plugins with', () => {
        expect(composables.useI18n).toBe(useI18n);
        expect(composables.useRoute).toBe(useRoute);
        expect(composables.useRouter).toBe(useRouter);
    });
});
