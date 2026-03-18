import { detectUnsupportedFeatures } from './transform-script';

export type MigrationStatus = 'fully-migratable' | 'partially-migratable' | 'not-migratable';

export interface ComponentAnalysis {
    componentName: string;
    status: MigrationStatus;
    blockers: string[];
}

export interface MigrationCategories {
    fullyMigratable: ComponentAnalysis[];
    partiallyMigratable: ComponentAnalysis[];
    notMigratable: ComponentAnalysis[];
}

/**
 * Hard blockers that prevent SFC generation entirely (render function).
 * These are distinct from soft blockers (mixins, extends) which allow partial migration.
 */
const HARD_BLOCKERS = new Set(['render function']);

/**
 * Analyses a single component's JS source and determines how far it can be
 * automatically migrated to an SFC / Composition API.
 *
 * - `fully-migratable`: no unsupported features — generates `<script setup>`
 * - `partially-migratable`: soft blockers present — generates `<script>` (Options API backoff)
 * - `not-migratable`: hard blockers present — file is skipped, manual work required
 */
export function analyzeComponent(componentName: string, jsContent: string): ComponentAnalysis {
    const blockers = detectUnsupportedFeatures(jsContent);

    const hasHardBlocker = blockers.some((b) => HARD_BLOCKERS.has(b));

    let status: MigrationStatus;
    if (hasHardBlocker) {
        status = 'not-migratable';
    } else if (blockers.length > 0) {
        status = 'partially-migratable';
    } else {
        status = 'fully-migratable';
    }

    return { componentName, status, blockers };
}

/**
 * Groups an array of component analyses into the three migration categories.
 */
export function categorizeComponents(analyses: ComponentAnalysis[]): MigrationCategories {
    return {
        fullyMigratable: analyses.filter((a) => a.status === 'fully-migratable'),
        partiallyMigratable: analyses.filter((a) => a.status === 'partially-migratable'),
        notMigratable: analyses.filter((a) => a.status === 'not-migratable'),
    };
}

/**
 * Generates a human-readable migration summary from categorized component analyses.
 */
export function generateSummary(categories: MigrationCategories): string {
    const total =
        categories.fullyMigratable.length +
        categories.partiallyMigratable.length +
        categories.notMigratable.length;

    if (total === 0) {
        return 'No components found.';
    }

    const lines: string[] = [
        `Migration Summary`,
        `=================`,
        `Total components analysed: ${total}`,
        '',
    ];

    lines.push(`Fully migrated (${categories.fullyMigratable.length}):`);
    if (categories.fullyMigratable.length > 0) {
        categories.fullyMigratable.forEach((c) => lines.push(`  ✓ ${c.componentName}`));
    } else {
        lines.push('  (none)');
    }
    lines.push('');

    lines.push(`Partially migrated — Options API backoff used (${categories.partiallyMigratable.length}):`);
    if (categories.partiallyMigratable.length > 0) {
        categories.partiallyMigratable.forEach((c) =>
            lines.push(`  ~ ${c.componentName} [blockers: ${c.blockers.join(', ')}]`),
        );
    } else {
        lines.push('  (none)');
    }
    lines.push('');

    lines.push(`Not migratable — manual migration required (${categories.notMigratable.length}):`);
    if (categories.notMigratable.length > 0) {
        categories.notMigratable.forEach((c) =>
            lines.push(`  ✗ ${c.componentName} [blockers: ${c.blockers.join(', ')}]`),
        );
    } else {
        lines.push('  (none)');
    }

    return lines.join('\n');
}
