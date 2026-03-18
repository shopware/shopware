import { Project, ScriptKind, SyntaxKind } from 'ts-morph';

// ---------------------------------------------------------------------------
// Public types
// ---------------------------------------------------------------------------

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

// ---------------------------------------------------------------------------
// Blocker definitions
// ---------------------------------------------------------------------------

/**
 * Hard blockers prevent SFC output entirely; soft blockers trigger an
 * Options API backoff into a plain `<script>` block.
 */
const HARD_BLOCKERS = new Set(['render function']);

// ---------------------------------------------------------------------------
// AST-based blocker detection
// ---------------------------------------------------------------------------

/**
 * Parses the component JS source with the TypeScript compiler (via ts-morph)
 * and inspects the Options API object literal for features that cannot be
 * automatically converted to Composition API.
 *
 * Using the AST avoids false positives from regex (e.g. the word "mixins"
 * inside a string, a comment, or a template literal).
 */
function detectBlockers(jsContent: string): string[] {
    const project = new Project({
        useInMemoryFileSystem: true,
        compilerOptions: { allowJs: true },
        skipAddingFilesFromTsConfig: true,
    });

    const sourceFile = project.createSourceFile('component.js', jsContent, {
        scriptKind: ScriptKind.JS,
    });

    const registerCall = sourceFile
        .getDescendantsOfKind(SyntaxKind.CallExpression)
        .find((call) => /Shopware\.Component\.(register|extend)/.test(call.getExpression().getText()));

    const blockers: string[] = [];

    // `extend()` itself is a soft blocker
    if (/Shopware\.Component\.extend/.test(registerCall?.getExpression().getText() ?? '')) {
        blockers.push('extends');
    }

    const secondArg = registerCall?.getArguments()[1];
    if (!secondArg?.isKind(SyntaxKind.ObjectLiteralExpression)) {
        return blockers;
    }

    const optionsObj = secondArg.asKindOrThrow(SyntaxKind.ObjectLiteralExpression);

    // `mixins: […]` → soft blocker (can't be automatically inlined)
    if (optionsObj.getProperty('mixins')) {
        blockers.push('mixins');
    }

    // `render()` → hard blocker (JSX/h() usage has no template equivalent)
    if (optionsObj.getProperty('render')) {
        blockers.push('render function');
    }

    return blockers;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Analyses a single component's JS source and determines how far it can be
 * automatically migrated to a Composition API SFC.
 */
export function analyzeComponent(componentName: string, jsContent: string): ComponentAnalysis {
    const blockers = detectBlockers(jsContent);
    const hasHardBlocker = blockers.some((b) => HARD_BLOCKERS.has(b));

    const status: MigrationStatus = hasHardBlocker
        ? 'not-migratable'
        : blockers.length > 0
          ? 'partially-migratable'
          : 'fully-migratable';

    return { componentName, status, blockers };
}

/**
 * Groups an array of analyses into the three migration categories.
 */
export function categorizeComponents(analyses: ComponentAnalysis[]): MigrationCategories {
    return {
        fullyMigratable: analyses.filter((a) => a.status === 'fully-migratable'),
        partiallyMigratable: analyses.filter((a) => a.status === 'partially-migratable'),
        notMigratable: analyses.filter((a) => a.status === 'not-migratable'),
    };
}

/**
 * Generates a human-readable migration summary from categorized analyses.
 */
export function generateSummary(categories: MigrationCategories): string {
    const total =
        categories.fullyMigratable.length +
        categories.partiallyMigratable.length +
        categories.notMigratable.length;

    if (total === 0) {
        return 'No components found.';
    }

    const fmtWithBlockers = (items: ComponentAnalysis[], symbol: string) =>
        items.length > 0
            ? items.map((c) => `  ${symbol} ${c.componentName} [blockers: ${c.blockers.join(', ')}]`)
            : ['  (none)'];

    const lines: string[] = [
        'Migration Summary',
        '=================',
        `Total components analysed: ${total}`,
        '',
        `Fully migrated (${categories.fullyMigratable.length}):`,
        ...categories.fullyMigratable.map((c) => `  ✓ ${c.componentName}`),
        '',
        `Partially migrated — Options API backoff used (${categories.partiallyMigratable.length}):`,
        ...fmtWithBlockers(categories.partiallyMigratable, '~'),
        '',
        `Not migratable — manual migration required (${categories.notMigratable.length}):`,
        ...fmtWithBlockers(categories.notMigratable, '✗'),
    ];

    return lines.join('\n');
}
