/**
 * @sw-package framework
 */

import { FIX_LEVELS } from './types';
import type { DeprecationMigration } from './types';

const VERSION_PATTERN = /^\d+(\.\d+){2,3}$/;

/**
 * Asserts the shape every registry migration needs: a unique non-empty id, versions, a description,
 * well-formed references and usage steps with a known fix level. Use it in the spec of each definition folder.
 *
 * @example
 * expectWellFormedMigrations(componentMigrations);
 */
export function expectWellFormedMigrations(migrations: DeprecationMigration[]): void {
    expect(migrations.length).toBeGreaterThan(0);

    const ids = migrations.map((migration) => migration.id);
    expect(new Set(ids).size).toBe(ids.length);

    migrations.forEach((migration) => {
        expect(migration.id).toMatch(/\S/);
        expect(migration.deprecatedIn).toMatch(VERSION_PATTERN);
        expect(migration.removedIn).toMatch(VERSION_PATTERN);
        expect(migration.description).toMatch(/\S/);
        expect(Array.isArray(migration.usage)).toBe(true);

        migration.references?.forEach((reference) => {
            expect(reference.type).toBe('upgrade');
            expect(reference.target).toMatch(/\S/);
        });

        migration.usage.forEach((usage) => {
            expect(FIX_LEVELS).toContain(usage.fix);

            if (usage.message !== undefined) {
                expect(usage.message).toMatch(/\S/);
            }
        });
    });
}
