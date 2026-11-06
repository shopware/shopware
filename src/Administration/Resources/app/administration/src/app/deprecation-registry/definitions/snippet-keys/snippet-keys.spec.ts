/**
 * @sw-package framework
 */
import migrations from './index';
import { expectWellFormedMigrations } from '../helpers/test-utils';

describe('src/app/deprecation-registry/definitions/snippet-keys', () => {
    it('has well-formed migrations', () => {
        expectWellFormedMigrations(migrations);
    });

    it('describes every usage step with a known kind and a source to match', () => {
        migrations.forEach((migration) => {
            expect(migration.usage.length).toBeGreaterThan(0);

            migration.usage.forEach((usage) => {
                expect(['remove-snippet-key']).toContain(usage.kind);
                expect(usage.from).toMatch(/\S/);
            });
        });
    });
});
