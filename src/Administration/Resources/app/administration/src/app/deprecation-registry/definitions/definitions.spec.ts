/**
 * @sw-package framework
 */
import fs from 'node:fs';
import path from 'node:path';
import assetMigrations from './assets';
import templateEventMigrations from './template-events';
import { expectWellFormedMigrations } from './helpers/test-utils';

const ADMINISTRATION_ROOT = path.resolve(__dirname, '../../../..');

describe('src/app/deprecation-registry/definitions', () => {
    describe('assets', () => {
        it('has well-formed migrations', () => {
            expectWellFormedMigrations(assetMigrations);
        });

        it('lists the deprecated asset files of every migration', () => {
            assetMigrations.forEach((migration) => {
                expect(migration.files?.length).toBeGreaterThan(0);

                migration.files?.forEach((file) => {
                    expect(file).toMatch(/^\/.+\.(png|jpe?g|webp)$/);
                });
            });
        });

        it('only uses asset usage steps', () => {
            assetMigrations.forEach((migration) => {
                migration.usage.forEach((usage) => {
                    expect([
                        'replace-extension',
                        'replace-asset',
                        'remove-asset',
                    ]).toContain(usage.kind);
                });
            });
        });

        it('points every replaced file to a file the Administration ships and keeps', () => {
            const deprecatedFiles = assetMigrations.flatMap((migration) => migration.files);
            const replacements = assetMigrations.flatMap((migration) => {
                return migration.usage.flatMap((usage) => {
                    if (usage.kind === 'replace-asset') {
                        return [usage.to];
                    }

                    if (usage.kind !== 'replace-extension') {
                        return [];
                    }

                    return migration.files.map((file) => {
                        const extension = path.extname(file);

                        expect(usage.from).toContain(extension);

                        return `${file.slice(0, -extension.length)}${usage.to}`;
                    });
                });
            });

            expect(replacements.length).toBeGreaterThan(0);
            replacements.forEach((file) => {
                expect(fs.existsSync(path.join(ADMINISTRATION_ROOT, file))).toBe(true);
            });
            // A replacement that is removed itself would need a second run and end in a manual report.
            expect(replacements.filter((file) => deprecatedFiles.includes(file))).toEqual([]);
        });
    });

    describe('template-events', () => {
        it('has well-formed migrations', () => {
            expectWellFormedMigrations(templateEventMigrations);
        });

        it('names the component, the old event and a different new event in every usage step', () => {
            templateEventMigrations.forEach((migration) => {
                expect(migration.usage.length).toBeGreaterThan(0);

                migration.usage.forEach((usage) => {
                    expect(usage.component).toMatch(/^[a-z][a-z0-9]*(-[a-z0-9]+)+$/);
                    expect(usage.from).toMatch(/\S/);
                    expect(usage.to).toMatch(/\S/);
                    expect(usage.to).not.toBe(usage.from);
                });
            });
        });
    });
});
