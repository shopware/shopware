/**
 * @sw-package framework
 */
import * as registry from './index';
import componentMigrations from './definitions/components';
import globalApiMigrations from './definitions/global-api';
import jsApiMigrations from './definitions/js-api';
import assetMigrations from './definitions/assets';
import templateBlockMigrations from './definitions/template-blocks';
import templateEventMigrations from './definitions/template-events';
import snippetKeyMigrations from './definitions/snippet-keys';
import packageMigrations from './definitions/packages';

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
});
