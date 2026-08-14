/**
 * @sw-package framework
 */

/**
 * Aggregates a dry run of the SFC migration codemod into a markdown report: which skip/TODO
 * reasons occur, how many components each one affects, and example components per reason.
 * The report is the planning input for deciding which features the codemod should learn next.
 *
 * Usage: npm run codemod:sfc-migration:analyze -- <path> [--out <file>]
 *
 * Reasons carry dynamic parts (binding names, twig snippets, error messages), so they are
 * normalized into stable groups first.
 */

import * as fs from 'fs';
import * as path from 'path';
import { runMigration, type MigrationResult } from './run-sfc-migration';

type ReasonRule = {
    pattern: RegExp;
    label: string | ((match: RegExpMatchArray) => string);
    stage: string;
};

type ComponentClass = MigrationResult['reports'][number]['registration'];

type ReasonGroup = {
    label: string;
    stage: string;
    /** Affected components, keyed by directory (names are not unique across directories). */
    components: Map<string, string>;
    /** Distinct affected components per registration class, counted like `components`. */
    classCounts: Map<ComponentClass, number>;
    occurrences: number;
};

const REPO_ROOT = path.resolve(__dirname, '../'.repeat(8));
const DEFAULT_OUT = path.join(REPO_ROOT, 'SFC-CODEMOD-SKIP-ANALYSIS.md');
const MAX_EXAMPLES = 3;
const COMPONENT_CLASSES: ComponentClass[] = [
    'register',
    'extend',
    'override',
    'unregistered',
];

// Ordered: the first matching rule wins; unmatched reasons fall through sanitized but ungrouped.
const SKIP_RULES: ReasonRule[] = [
    {
        pattern: /^no template import/,
        label: 'no template import (render function or inherited template)',
        stage: 'precheck',
    },
    {
        pattern: /^template imported from outside/,
        label: 'template imported from outside the component directory',
        stage: 'precheck',
    },
    {
        pattern: /^(component|directory) name is not multi-segment kebab-case$/,
        label: 'component name is not multi-segment kebab-case',
        stage: 'precheck',
    },
    {
        pattern: /^template filename does not match the registered component name$/,
        label: 'template filename does not match the registered component name',
        stage: 'precheck',
    },
    {
        pattern: /^component name registered more than once$/,
        label: 'component name registered more than once',
        stage: 'precheck',
    },
    { pattern: /^template file not found$/, label: 'template file not found', stage: 'precheck' },
    {
        pattern: /^unsupported twig syntax: \{[%#]-?\s*([\w-]+)/,
        label: (match) => `unsupported twig syntax: \`{% ${match[1]} %}\``,
        stage: 'template',
    },
    { pattern: /^unsupported twig syntax:/, label: 'unsupported twig syntax: other', stage: 'template' },
    {
        pattern: /^orphaned cross-block v-else \(no preceding v-if\)$/,
        label: 'orphaned cross-block v-else (no preceding v-if)',
        stage: 'template',
    },
    {
        pattern: /^script parse error: (.+)$/,
        label: (match) => `script parse error: ${sanitizeMessage(match[1])}`,
        stage: 'script',
    },
    { pattern: /^no default export$/, label: 'no default export', stage: 'script' },
    { pattern: /^unsupported default export shape$/, label: 'unsupported default export shape', stage: 'script' },
    { pattern: /^binding '.+' uses a reserved name$/, label: 'binding uses a reserved name', stage: 'script' },
    {
        pattern: /^binding '.+' collides with a generated helper$/,
        label: 'binding collides with a generated helper',
        stage: 'script',
    },
    {
        pattern: /^'.+' is declared as both a prop and a component member$/,
        label: 'same name declared as prop and component member',
        stage: 'script',
    },
    {
        pattern: /^name '.+' does not match the directory name$/,
        label: 'component name does not match the directory name',
        stage: 'script',
    },
    { pattern: /^(mixins|render|renderError)$/, label: (match) => `option \`${match[1]}\``, stage: 'script' },
    { pattern: /^this\.(\$super|\$parent)$/, label: (match) => `\`this.${match[1]}\` usage`, stage: 'script' },
    { pattern: /^prettier: ([\s\S]+)$/, label: (match) => `prettier: ${sanitizeMessage(match[1])}`, stage: 'validation' },
    {
        pattern: /^validation: ([\s\S]+)$/,
        label: (match) => `validation: ${sanitizeMessage(match[1])}`,
        stage: 'validation',
    },
];

const TODO_RULES: ReasonRule[] = [
    {
        pattern: /^convert '([\w-]+)' manually$/,
        label: (match) => `option \`${match[1]}\` needs manual conversion`,
        stage: 'option',
    },
    { pattern: /^unknown option '(.+)'$/, label: (match) => `unknown option \`${match[1]}\``, stage: 'option' },
    { pattern: /^unmapped this\.(\$\w+)$/, label: (match) => `unmapped \`this.${match[1]}\``, stage: 'this-rewrite' },
    { pattern: /^unmapped this\./, label: 'unmapped `this.<member>` (no matching binding)', stage: 'this-rewrite' },
    { pattern: /inside a nested function/, label: '`this` inside a nested function (own `this`)', stage: 'this-rewrite' },
    { pattern: /^bare `this` usage$/, label: 'bare `this` usage', stage: 'this-rewrite' },
    { pattern: /^dynamic `this\[\.\.\.\]` access$/, label: 'dynamic `this[...]` access', stage: 'this-rewrite' },
    { pattern: /^dynamic this\.\$refs access$/, label: 'dynamic `this.$refs` access', stage: 'this-rewrite' },
    { pattern: /^dynamic \$emit event name$/, label: 'dynamic `$emit` event name', stage: 'this-rewrite' },
    {
        pattern: /^template ref '.+' collides with an existing binding$/,
        label: 'template ref collides with an existing binding',
        stage: 'this-rewrite',
    },
    {
        pattern: /^unsupported (computed|methods) entry '.+'$/,
        label: (match) => `unsupported ${match[1]} entry`,
        stage: 'option',
    },
    {
        pattern: /^(computed|methods) entry '.+' is not a plain function$/,
        label: (match) => `${match[1]} entry is not a plain function`,
        stage: 'option',
    },
    { pattern: /^unsupported watch entry/, label: 'unsupported watch entry', stage: 'option' },
    {
        pattern: /^watch source '.+' is not a known prop, data or computed$/,
        label: 'watch source is not a known prop, data or computed',
        stage: 'option',
    },
    { pattern: /^spread in (computed|methods)$/, label: (match) => `spread in ${match[1]}`, stage: 'option' },
    {
        pattern: /^unsupported inject declaration/,
        label: 'unsupported inject declaration (only the array form is migrated)',
        stage: 'option',
    },
    { pattern: /^data\(\)/, label: 'data() does not directly return an object literal', stage: 'option' },
    {
        pattern: /^unsupported (data\(\)|props) /,
        label: (match) => `unsupported ${match[1]} declaration/entry`,
        stage: 'option',
    },
    { pattern: /^non-literal inheritAttrs$/, label: 'non-literal inheritAttrs', stage: 'option' },
    { pattern: /^\w+ is not a plain function$/, label: 'lifecycle/created hook is not a plain function', stage: 'option' },
];

/**
 * Makes a toolchain message table-safe and groupable: first line only, `(12:34)` source positions
 * stripped, raw tags like `<script setup>` escaped so rendered markdown does not swallow them.
 */
function sanitizeMessage(message: string): string {
    return message
        .split('\n')[0]
        .replace(/\s*\(\d+:\d+\)\s*/g, ' ')
        .replaceAll('<', '\\<')
        .trim();
}

function normalizeReason(reason: string, rules: ReasonRule[]): { label: string; stage: string } {
    for (const rule of rules) {
        const match = reason.match(rule.pattern);

        if (match) {
            return { label: typeof rule.label === 'string' ? rule.label : rule.label(match), stage: rule.stage };
        }
    }

    // First line only — an unmatched toolchain message may carry a multi-line code frame that
    // would break the markdown table.
    return { label: sanitizeMessage(reason), stage: 'other' };
}

function collectGroups(
    reports: MigrationResult['reports'],
    outcome: 'skipped' | 'partial',
    rules: ReasonRule[],
): ReasonGroup[] {
    const groups = new Map<string, ReasonGroup>();

    for (const entry of reports) {
        if (entry.outcome !== outcome) {
            continue;
        }

        for (const reason of entry.reasons) {
            const { label, stage } = normalizeReason(reason, rules);
            const group = groups.get(label) ?? {
                label,
                stage,
                components: new Map<string, string>(),
                classCounts: new Map<ComponentClass, number>(),
                occurrences: 0,
            };

            if (!group.components.has(entry.dir)) {
                group.classCounts.set(entry.registration, (group.classCounts.get(entry.registration) ?? 0) + 1);
            }

            group.components.set(entry.dir, entry.name);
            group.occurrences += 1;
            groups.set(label, group);
        }
    }

    return [...groups.values()].sort((a, b) => b.components.size - a.components.size);
}

function exampleList(values: Iterable<string>, total: number): string {
    const examples = [...values].slice(0, MAX_EXAMPLES).map((value) => `\`${value}\``);
    const remaining = total - examples.length;

    return examples.join(', ') + (remaining > 0 ? `, … (+${remaining})` : '');
}

function countClasses(reports: MigrationResult['reports']): Map<ComponentClass, number> {
    const counts = new Map<ComponentClass, number>();

    for (const entry of reports) {
        counts.set(entry.registration, (counts.get(entry.registration) ?? 0) + 1);
    }

    return counts;
}

/**
 * Class columns of the reason tables: the three classes every component falls into, plus `override`
 * only when a directory really is registered through one (otherwise a column of zeros).
 */
function classColumns(classCounts: Map<ComponentClass, number>): ComponentClass[] {
    return COMPONENT_CLASSES.filter(
        (componentClass) => componentClass !== 'override' || (classCounts.get(componentClass) ?? 0) > 0,
    );
}

function renderGroupTable(groups: ReasonGroup[], classes: ComponentClass[], showOccurrences: boolean): string[] {
    const lines = [
        `| Reason | Stage | Components |${classes.map((componentClass) => ` ${componentClass} |`).join('')}` +
            `${showOccurrences ? ' Occurrences |' : ''} Examples |`,
        `|---|---|---:|${classes.map(() => '---:|').join('')}${showOccurrences ? '---:|' : ''}---|`,
    ];

    for (const group of groups) {
        lines.push(
            `| ${group.label.replaceAll('|', '\\|')} | ${group.stage} | ${group.components.size} |` +
                `${classes.map((componentClass) => ` ${group.classCounts.get(componentClass) ?? 0} |`).join('')}` +
                `${showOccurrences ? ` ${group.occurrences} |` : ''}` +
                ` ${exampleList(group.components.values(), group.components.size)} |`,
        );
    }

    return lines;
}

function buildReport(result: MigrationResult, target: string, generatedAt: string): string {
    const { stats, reports, inlineOverrides } = result;
    const total = stats.full + stats.partial + stats.skipped + stats.alreadyMigrated + stats.error;
    const skipGroups = collectGroups(reports, 'skipped', SKIP_RULES);
    const todoGroups = collectGroups(reports, 'partial', TODO_RULES);
    const errors = reports.filter((entry) => entry.outcome === 'error');
    const classCounts = countClasses(reports);
    const classes = classColumns(classCounts);

    const lines = [
        '# SFC Codemod — Skip & TODO Analysis',
        '',
        `Dry run over \`${target}\` on ${generatedAt}, generated by \`scripts/codemods/sfc-migration/analyze-skips.ts\`.`,
        '',
        '## Summary',
        '',
        '| Outcome | Components |',
        '|---|---:|',
        `| full | ${stats.full} |`,
        `| partial | ${stats.partial} |`,
        `| skipped | ${stats.skipped} |`,
        `| already migrated | ${stats.alreadyMigrated} |`,
        `| error | ${stats.error} |`,
        `| **total** | **${total}** |`,
        '',
        '| Class | Components |',
        '|---|---:|',
        ...COMPONENT_CLASSES.filter((componentClass) => (classCounts.get(componentClass) ?? 0) > 0).map(
            (componentClass) => `| ${componentClass} | ${classCounts.get(componentClass)} |`,
        ),
        '',
        ...(inlineOverrides.length > 0
            ? [
                  `Additionally, ${inlineOverrides.length} inline \`Component.override(...)\` configs were found — ` +
                      'invisible to the codemod, reported only.',
                  '',
              ]
            : []),
        `## Skip reasons (${stats.skipped} components)`,
        '',
        'A component can be skipped for several reasons at once, so the component counts add up to more than the number of skipped components.',
        'Stages: precheck (file layout before conversion), template (twig transform), script (Options API transform), validation (generated SFC rejected).',
        'The class columns split the component count by how the component is registered.',
        '',
        ...renderGroupTable(skipGroups, classes, false),
        '',
        `## Partial-migration TODO reasons (${stats.partial} components)`,
        '',
        'These components migrate with `TODO(sfc-migration)` comments left in the draft. "Occurrences" counts every TODO, "Components" counts distinct components.',
        '',
        ...renderGroupTable(todoGroups, classes, true),
        '',
        `## Errors (${stats.error} components)`,
        '',
        '| Component | Error |',
        '|---|---|',
        ...errors.map(
            (entry) => `| \`${entry.name}\` | ${entry.reasons.map(sanitizeMessage).join(', ').replaceAll('|', '\\|')} |`,
        ),
        '',
    ];

    return lines.join('\n');
}

function main(): void {
    const args = process.argv.slice(2);
    const outFlagIndex = args.indexOf('--out');
    const outFile = outFlagIndex !== -1 ? args[outFlagIndex + 1] : undefined;
    const positional = args.filter(
        (arg, index) => !arg.startsWith('--') && (outFlagIndex === -1 || index !== outFlagIndex + 1),
    );

    if (positional.length !== 1 || (outFlagIndex !== -1 && !outFile)) {
        console.error('Usage: npm run codemod:sfc-migration:analyze -- <path> [--out <file>]');
        process.exitCode = 1;
        return;
    }

    const targetDir = path.resolve(positional[0]);

    if (!fs.existsSync(targetDir) || !fs.statSync(targetDir).isDirectory()) {
        console.error(`Not a directory: ${targetDir}`);
        process.exitCode = 1;
        return;
    }

    runMigration(targetDir, { write: false })
        .then((result) => {
            const outPath = path.resolve(outFile ?? DEFAULT_OUT);
            const report = buildReport(result, path.relative(REPO_ROOT, targetDir), new Date().toISOString().slice(0, 10));

            fs.writeFileSync(outPath, report);
            console.log(`Report written to ${outPath}`);
        })
        .catch((error) => {
            console.error(error);
            process.exitCode = 1;
        });
}

if (require.main === module) {
    main();
}

export { normalizeReason, collectGroups, buildReport, SKIP_RULES, TODO_RULES };
