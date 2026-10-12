/**
 * @sw-package framework
 */
import assetMigrations from './assets';
import templateEventMigrations from './template-events';
import { expectWellFormedMigrations } from './helpers/test-utils';

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
                        'remove-asset',
                    ]).toContain(usage.kind);
                });
            });
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
