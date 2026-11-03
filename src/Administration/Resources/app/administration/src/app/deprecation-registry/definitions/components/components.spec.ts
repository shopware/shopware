/**
 * @sw-package framework
 */
import componentMigrations from './index';
import { expectWellFormedMigrations } from '../helpers/test-utils';

const COMPONENT_LEVEL_KINDS = [
    'rename-component',
    'codemod-rule',
];

describe('src/app/deprecation-registry/definitions/components', () => {
    it('has well-formed migrations', () => {
        expectWellFormedMigrations(componentMigrations);
    });

    it('targets each deprecated component once', () => {
        const components = componentMigrations.map((migration) => migration.component);

        components.forEach((component) => {
            expect(component).toMatch(/^sw-[a-z0-9-]+$/);
        });
        expect(new Set(components).size).toBe(components.length);
    });

    it.each(componentMigrations.map((migration) => [migration.id, migration]))(
        'lets the usage steps of "%s" either replace the component first or carry an ESLint check',
        (_id, migration) => {
            expect(migration.usage.length).toBeGreaterThan(0);

            const isComponentStep = (usage: (typeof migration.usage)[number]) =>
                COMPONENT_LEVEL_KINDS.includes(usage.kind ?? '');
            const componentSteps = migration.usage.filter(isComponentStep);
            const usageSteps = migration.usage.filter((usage) => !isComponentStep(usage));

            expect(componentSteps).toEqual(migration.usage.slice(0, componentSteps.length));
            expect(componentSteps.length).toBeLessThanOrEqual(1);
            componentSteps.forEach((usage) => {
                expect(usage.from).toBe(migration.component);
                expect(usage.to).toMatch(/^mt-[a-z0-9-]+$/);
            });
            usageSteps.forEach((usage) => {
                expect(typeof usage.kind).toBe('string');
                expect(typeof usage.eslint?.report).toBe('function');
            });
        },
    );

    it.each(componentMigrations.filter((migration) => migration.replacement).map((migration) => [migration.id, migration]))(
        'converts "%s" to its replacement in the first step',
        (_id, migration) => {
            expect(COMPONENT_LEVEL_KINDS).toContain(migration.usage[0].kind);
            expect(migration.usage[0].to).toBe(migration.replacement);
        },
    );
});
