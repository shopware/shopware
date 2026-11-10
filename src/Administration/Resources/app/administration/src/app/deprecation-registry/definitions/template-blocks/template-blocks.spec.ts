/**
 * @sw-package framework
 */
import migrations from './index';
import { expectWellFormedMigrations } from '../helpers/test-utils';
import type { RemoveTemplateBlockStep, RenameTemplateBlockStep } from '../helpers';

type TemplateBlockStep = RemoveTemplateBlockStep | RenameTemplateBlockStep;

const steps: TemplateBlockStep[] = migrations.flatMap((migration) => migration.usage);

/** The components a step applies to; `*` for a step without `component`, which applies everywhere. */
function scopeOf(step: TemplateBlockStep): string[] {
    return step.component ? [step.component, ...(step.extendedBy ?? [])] : ['*'];
}

function overlaps(left: string[], right: string[]): boolean {
    return left.includes('*') || right.includes('*') || left.some((name) => right.includes(name));
}

function describeStep(step: TemplateBlockStep): string {
    return `${step.kind} ${step.from}${step.component ? ` of ${step.component}` : ''}`;
}

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
                expect(usage.from).toMatch(/^[a-z0-9_]+$/);
            });
        });
    });

    it('gives every renamed block a different target block', () => {
        steps
            .filter((usage): usage is RenameTemplateBlockStep => usage.kind === 'rename-template-block')
            .forEach((usage) => {
                expect(usage.to).toMatch(/^[a-z0-9_]+$/);
                expect(usage.to).not.toBe(usage.from);
            });
    });

    it('renames blocks automatically and leaves removed blocks to a manual migration', () => {
        steps.forEach((usage) => {
            expect(usage.fix).toBe(usage.kind === 'rename-template-block' ? 'auto' : 'manual');
        });
    });

    it('scopes steps by component names', () => {
        steps.forEach((usage) => {
            [
                usage.component,
                ...(usage.extendedBy ?? []),
            ]
                .filter((name) => name !== undefined)
                .forEach((name) => {
                    expect(name).toMatch(/^[a-z][a-z0-9]*(-[a-z0-9]+)+$/);
                });
        });

        steps
            .filter((usage) => usage.extendedBy !== undefined)
            .forEach((usage) => {
                expect(usage.component).toBeDefined();
            });
    });

    it('defines every block at most once per component', () => {
        const conflicts = steps.flatMap((step, index) => {
            return steps
                .slice(index + 1)
                .filter((other) => other.from === step.from && overlaps(scopeOf(step), scopeOf(other)))
                .map((other) => `${describeStep(step)} / ${describeStep(other)}`);
        });

        expect(conflicts).toEqual([]);
    });

    it('never points an override to a block that is renamed or removed itself', () => {
        const deprecatedTargets = steps.flatMap((step) => {
            if (!step.to) {
                return [];
            }

            return (
                steps
                    .filter((other) => other.from === step.to)
                    // A target of an unscoped step may share its name with a deprecated block of another component.
                    .filter((other) => !other.component || (step.component && overlaps(scopeOf(step), scopeOf(other))))
                    .map((other) => `${describeStep(step)} -> ${describeStep(other)}`)
            );
        });

        expect(deprecatedTargets).toEqual([]);
    });

    it('names the replacement of every slot variable the new block does not provide', () => {
        const slotVariableSteps = steps.filter((usage): usage is RenameTemplateBlockStep => {
            return usage.kind === 'rename-template-block' && usage.removedSlotVariables !== undefined;
        });

        expect(slotVariableSteps.length).toBeGreaterThan(0);
        slotVariableSteps.forEach((usage) => {
            expect(usage.removedSlotVariables).toEqual(['active']);
            expect(usage.message).toContain('activeTab');
        });
    });
});
