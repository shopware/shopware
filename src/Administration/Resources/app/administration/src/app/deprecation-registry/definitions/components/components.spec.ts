/**
 * @sw-package framework
 */
import componentMigrations from './index';
import { expectWellFormedMigrations } from '../helpers/test-utils';
import type { ComponentStep, RenameComponentStep } from '../helpers';

function isComponentStep(usage: ComponentStep): usage is RenameComponentStep {
    return usage.kind === 'rename-component';
}

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
        'lets the usage steps of "%s" replace the component first, at most once',
        (_id, migration) => {
            expect(migration.usage.length).toBeGreaterThan(0);

            const componentSteps = migration.usage.filter(isComponentStep);

            expect(componentSteps).toEqual(migration.usage.slice(0, componentSteps.length));
            expect(componentSteps.length).toBeLessThanOrEqual(1);
            componentSteps.forEach((usage) => {
                expect(usage.from).toBe(migration.component);
                expect(usage.to).toMatch(/^(mt|sw)-[a-z0-9-]+$/);
            });
        },
    );

    it.each(componentMigrations.filter((migration) => migration.replacement).map((migration) => [migration.id, migration]))(
        'converts "%s" to its replacement in the first step',
        (_id, migration) => {
            const [firstStep] = migration.usage;

            expect(isComponentStep(firstStep)).toBe(true);
            expect(isComponentStep(firstStep) && firstStep.to).toBe(migration.replacement);
        },
    );
});
