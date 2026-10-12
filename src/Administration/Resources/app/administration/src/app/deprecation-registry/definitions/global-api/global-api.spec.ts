/**
 * @sw-package framework
 */
import migrations from './index';
import { expectWellFormedMigrations } from '../helpers/test-utils';

describe('src/app/deprecation-registry/definitions/global-api', () => {
    it('has well-formed migrations', () => {
        expectWellFormedMigrations(migrations);
    });

    it('describes every usage step with a known kind and a source to match', () => {
        migrations.forEach((migration) => {
            expect(migration.usage.length).toBeGreaterThan(0);

            migration.usage.forEach((usage) => {
                expect(['rename-call']).toContain(usage.kind);
                expect(usage.from).toMatch(/\S/);
            });
        });
    });

    it('names the global API each migration replaces', () => {
        migrations.forEach((migration) => {
            expect(migration.api).toMatch(/\S/);
            expect(migration.usage.map((usage) => usage.from)).toContain(migration.api);
        });
    });
});
