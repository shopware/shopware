/**
 * @sw-package framework
 */
import migrations from './index';
import { expectWellFormedMigrations } from '../helpers/test-utils';

describe('src/app/deprecation-registry/definitions/packages', () => {
    it('has well-formed migrations', () => {
        expectWellFormedMigrations(migrations);
    });

    it('describes every usage step with a known kind and a source to match', () => {
        migrations.forEach((migration) => {
            expect(migration.usage.length).toBeGreaterThan(0);

            migration.usage.forEach((usage) => {
                expect(['rename-package']).toContain(usage.kind);
                expect(usage.from).toMatch(/\S/);
            });
        });
    });

    it('renames each package to a different package', () => {
        migrations
            .flatMap((migration) => migration.usage)
            .forEach((usage) => {
                expect(usage.to).toMatch(/^@[a-z0-9-]+\/[a-z0-9-]+$/);
                expect(usage.to).not.toBe(usage.from);
            });
    });
});
