/**
 * @sw-package framework
 */
import fs from 'node:fs';
import path from 'node:path';
import composables from './index';

jest.unmock('./index');

const MIXIN_REPLACEMENT = /stableVersion:v6\.9\.0 feature:ADMIN_MIXIN_COMPOSABLES/;

/** The default export name of every composable whose file is tagged as a mixin replacement. */
function taggedComposables(): string[] {
    return fs
        .readdirSync(__dirname)
        .filter((file) => /^use-.*\.ts$/.test(file) && !file.endsWith('.spec.ts'))
        .map((file) => fs.readFileSync(path.join(__dirname, file), 'utf8'))
        .filter((source) => MIXIN_REPLACEMENT.test(source))
        .map((source) => /export default function (\w+)/.exec(source)?.[1])
        .filter((name): name is string => name !== undefined);
}

describe('src/app/composables/index', () => {
    it('publishes exactly the composables tagged as mixin replacements', () => {
        expect(Object.keys(composables).sort()).toEqual(taggedComposables().sort());
    });
});
