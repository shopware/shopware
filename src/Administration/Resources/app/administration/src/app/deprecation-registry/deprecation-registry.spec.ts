/**
 * @sw-package framework
 */
import fs from 'node:fs';
import path from 'node:path';
import * as registry from './index';
import componentMigrations from './definitions/components';
import globalApiMigrations from './definitions/global-api';
import jsApiMigrations from './definitions/js-api';
import assetMigrations from './definitions/assets';
import templateBlockMigrations from './definitions/template-blocks';
import templateEventMigrations from './definitions/template-events';
import snippetKeyMigrations from './definitions/snippet-keys';
import packageMigrations from './definitions/packages';

const REPOSITORY_ROOT = path.resolve(__dirname, '../../../../../../../..');

/**
 * Turns a markdown heading into the anchor GitHub generates for it.
 *
 * @example
 * toGitHubAnchor('`sw-empty-state` removed'); // 'sw-empty-state-removed'
 */
function toGitHubAnchor(heading: string): string {
    return heading
        .trim()
        .toLowerCase()
        .replace(/[^\w\- ]/g, '')
        .replace(/ /g, '-');
}

function getHeadingAnchors(markdownFile: string): string[] {
    return fs
        .readFileSync(path.join(REPOSITORY_ROOT, markdownFile), 'utf8')
        .split('\n')
        .filter((line) => line.startsWith('#'))
        .map((line) => toGitHubAnchor(line.replace(/^#+/, '')));
}

describe('src/app/deprecation-registry', () => {
    it('exports one migration list per definition folder', () => {
        expect(registry).toEqual({
            componentApiMigrations: componentMigrations,
            globalApiMigrations,
            jsApiMigrations,
            assetMigrations,
            templateBlockMigrations,
            templateEventMigrations,
            snippetKeyMigrations,
            packageMigrations,
        });
    });

    it('uses every migration id only once across the registry', () => {
        const ids = Object.values(registry).flatMap((migrations) => migrations.map((migration) => migration.id));

        expect(ids.length).toBeGreaterThan(0);
        expect(new Set(ids).size).toBe(ids.length);
    });

    it('links every upgrade reference to an existing UPGRADE heading', () => {
        const upgradeTargets = Object.values(registry)
            .flat()
            .flatMap((migration) => migration.references ?? [])
            .filter((reference) => reference.type === 'upgrade')
            .map((reference) => reference.target);

        expect(upgradeTargets.length).toBeGreaterThan(0);
        upgradeTargets.forEach((target) => {
            const [file, anchor] = target.split('#');

            expect(getHeadingAnchors(file)).toContain(anchor);
        });
    });
});
