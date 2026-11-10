/**
 * @sw-package framework
 *
 * Reads migrations from the codemod data of a Shopware version, `eslint-rules/deprecation-rules/data/<version>`.
 * Its README.md documents the format: the fields of a migration, with UPGRADE anchors in `upgrade`, and
 * one usage per line, with the step kind in `type` and the fields of its step builder.
 */

import { upgradeGuide } from './migrations';
import type { DeprecationReference, FixLevel, StepConfig } from './types';

type DataStep = { kind: string; fix: FixLevel; message?: string };

type StepOfKind<Step extends DataStep, Kind> = Extract<Step, { kind: Kind }>;

/** The step builder of every step kind of a category; a builder sets the default `fix` of its kind. */
type StepBuilders<Step extends DataStep> = {
    [Kind in Step['kind']]: (config: StepConfig<StepOfKind<Step, Kind>>) => StepOfKind<Step, Kind>;
};

type UsageData<Step extends DataStep> = {
    [Kind in Step['kind']]: StepConfig<StepOfKind<Step, Kind>> & { type: Kind };
}[Step['kind']];

/** A migration as the data holds it. */
export type MigrationData<Step extends DataStep> = {
    id: string;
    deprecatedIn: string;
    removedIn: string;
    description: string;
    upgrade: string[];
    usage: UsageData<Step>[];
};

type DataMigration<Step extends DataStep> = Omit<MigrationData<Step>, 'upgrade' | 'usage'> & {
    references: DeprecationReference[];
    usage: Step[];
};

/**
 * A builder as `migrationsFromData` calls it. The `type` of a usage picks the builder of its fields,
 * which TypeScript cannot follow through the destructuring; a method signature takes any of them.
 */
type BuilderOfType<Step extends DataStep> = { build(config: object): Step }['build'];

/**
 * Turns the data entries into migrations: `upgrade` into references, every usage into its step.
 *
 * @example
 * migrationsFromData(apiUsageData as MigrationData<ApiUsageStep>[], { 'member-usage': memberUsage, ... });
 */
export function migrationsFromData<Step extends DataStep>(
    entries: MigrationData<Step>[],
    builders: StepBuilders<Step>,
): DataMigration<Step>[] {
    const buildersByType: Record<Step['kind'], BuilderOfType<Step>> = builders;

    return entries.map(({ upgrade, usage, ...migration }) => ({
        ...migration,
        references: upgrade.map(upgradeGuide),
        usage: usage.map(({ type, ...config }) => buildersByType[type](config)),
    }));
}
