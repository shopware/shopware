/**
 * @sw-package framework
 */
import migrations from './index';
import { GROUPS, SHARED } from './removed-in-6-8';
import { RENAMES } from './renamed-in-6-8';
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

    describe('removed blocks data', () => {
        const names = GROUPS.flatMap((group) => Object.keys(group.blocks));

        it('describes every replacement in a known form', () => {
            GROUPS.forEach((group) => {
                expect(['tabs', 'removed', 'component']).toContain(group.reason);
                expect(group.component).toMatch(/^sw-[a-z0-9-]+$/);

                Object.values(group.blocks).forEach((replacement) => {
                    if (replacement === null || typeof replacement === 'string') {
                        return;
                    }

                    const kinds = [
                        'use',
                        'extend',
                        'tab',
                        'hint',
                    ].filter((key) => {
                        return typeof (replacement as Record<string, unknown>)[key] === 'string';
                    });

                    expect(kinds).toHaveLength(1);
                });
            });
        });

        it('lists only shared names that a group removes', () => {
            Object.keys(SHARED).forEach((name) => {
                expect(names).toContain(name);
            });
        });

        it('marks names that several groups remove as shared', () => {
            names
                .filter((name, index) => names.indexOf(name) !== index)
                .forEach((name) => {
                    expect(Object.keys(SHARED)).toContain(name);
                });
        });
    });

    describe('renamed blocks data', () => {
        it('gives every entry either a new name or a 6.8 target', () => {
            RENAMES.forEach((rename) => {
                const kinds = (
                    [
                        'to',
                        'use',
                        'tab',
                        'hint',
                    ] as const
                ).filter((key) => rename[key]);

                expect([rename.from, kinds]).toEqual([rename.from, [kinds[0]]]);
                expect(rename.component).toMatch(/^sw-[a-z0-9-]+$/);
            });
        });

        it('marks names that several entries use as shared', () => {
            RENAMES.filter((rename) => RENAMES.filter(({ from }) => from === rename.from).length > 1).forEach((rename) => {
                expect([rename.component, rename.from, rename.shared]).toEqual([rename.component, rename.from, true]);
            });
        });

        it('does not list a block of a component as renamed and removed', () => {
            const removed = new Set(
                GROUPS.flatMap((group) => Object.keys(group.blocks).map((name) => `${group.component}:${name}`)),
            );

            expect(RENAMES.filter((rename) => removed.has(`${rename.component}:${rename.from}`))).toEqual([]);
        });
    });
});
