/**
 * @sw-package framework
 */
import migrations from './index';
import { expectWellFormedMigrations } from '../helpers/test-utils';

describe('src/app/deprecation-registry/definitions/js-api', () => {
    it('has well-formed migrations', () => {
        expectWellFormedMigrations(migrations);
    });

    it('describes every usage step with a known kind and a source to match', () => {
        migrations.forEach((migration) => {
            expect(migration.usage.length).toBeGreaterThan(0);

            migration.usage.forEach((usage) => {
                expect([
                    'replace-object-option',
                    'member-call',
                ]).toContain(usage.kind);
                expect(usage.from).toMatch(/\S/);
            });
        });
    });

    it('keeps the deprecated JavaScript API calls report-only', () => {
        const memberCalls = migrations
            .flatMap((migration) => migration.usage)
            .filter((usage) => usage.kind === 'member-call');

        expect(memberCalls.length).toBeGreaterThan(0);
        memberCalls.forEach((usage) => {
            expect(usage.fix).toBe('manual');
        });
    });
});
