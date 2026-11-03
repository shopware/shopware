/**
 * @sw-package framework
 */
import migrations from './index';
import { expectWellFormedMigrations } from '../helpers/test-utils';
import type { RenameTemplateBlockStep } from '../helpers';

describe('src/app/deprecation-registry/definitions/template-blocks', () => {
    it('has well-formed migrations', () => {
        expectWellFormedMigrations(migrations);
    });

    it('describes every usage step with a known kind and a source to match', () => {
        migrations.forEach((migration) => {
            expect(migration.usage.length).toBeGreaterThan(0);

            migration.usage.forEach((usage) => {
                expect([
                    'remove-template-block',
                    'rename-template-block',
                ]).toContain(usage.kind);
                expect(usage.from).toMatch(/\S/);
            });
        });
    });

    it('gives every renamed block a different target block', () => {
        migrations
            .flatMap((migration) => migration.usage)
            .filter((usage): usage is RenameTemplateBlockStep => usage.kind === 'rename-template-block')
            .forEach((usage) => {
                expect(usage.to).toMatch(/^[a-z0-9_]+$/);
                expect(usage.to).not.toBe(usage.from);
            });
    });
});
