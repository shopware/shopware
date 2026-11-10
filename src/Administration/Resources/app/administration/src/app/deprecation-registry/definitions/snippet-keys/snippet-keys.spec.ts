/**
 * @sw-package framework
 */
import globalSnippets from 'src/app/snippet/en.json';
import migrations from './index';
import { expectWellFormedMigrations } from '../helpers/test-utils';

/**
 * Reads a dotted snippet key from a snippet file.
 *
 * @example
 * getSnippet({ a: { b: 'B' } }, 'a.b'); // 'B'
 */
function getSnippet(snippets: object, key: string): unknown {
    return key.split('.').reduce<unknown>((node, part) => {
        return node && typeof node === 'object' ? (node as Record<string, unknown>)[part] : undefined;
    }, snippets);
}

describe('src/app/deprecation-registry/definitions/snippet-keys', () => {
    it('has well-formed migrations', () => {
        expectWellFormedMigrations(migrations);
    });

    it('describes every usage step with a known kind and a source to match', () => {
        migrations.forEach((migration) => {
            expect(migration.usage.length).toBeGreaterThan(0);

            migration.usage.forEach((usage) => {
                expect([
                    'remove-snippet-key',
                    'rename-snippet-key',
                ]).toContain(usage.kind);
                expect(usage.from).toMatch(/\S/);
            });
        });
    });

    it('renames global condition keys to keys the Administration defines', () => {
        const renames = migrations
            .flatMap((migration) => migration.usage)
            .flatMap((usage) => (usage.kind === 'rename-snippet-key' ? [usage] : []));

        expect(renames.length).toBeGreaterThan(0);
        renames.forEach((usage) => {
            expect(typeof getSnippet(globalSnippets, usage.to)).toBe('string');
            expect(usage.to).not.toBe(usage.from);
        });
    });

    it('keeps the condition labels that stay out of every step', () => {
        const keys = migrations.flatMap((migration) => migration.usage.map((usage) => usage.from));
        const stayingLabels = [
            'global.sw-condition.condition.cartTaxDisplay.label',
            'global.sw-condition.condition.lineItemOfTypeRule.label',
            'global.sw-condition.condition.dayOfWeekRule',
        ];

        stayingLabels.forEach((label) => {
            expect(keys.some((key) => label === key || label.startsWith(`${key}.`))).toBe(false);
        });
    });
});
