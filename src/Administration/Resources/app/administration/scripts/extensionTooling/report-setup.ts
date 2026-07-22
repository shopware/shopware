/**
 * @sw-package framework
 *
 * Human-readable rendering for `admin:setup-extension-tooling`. Pure: takes the
 * setup result and returns the full report string. Color is applied via
 * picocolors (see report-check.ts for the color-support contract the specs pin).
 * The report classifies each extension by derived state and, unless --explain
 * collapses into a full breakdown, keeps the concise output focused on what a
 * developer must do next.
 */

import colors from 'picocolors';
import { classifyFile, describeNextStep } from './report-guidance';
import type { FileClass } from './report-guidance';
import type { SetupExtensionToolingResult } from './setup';
import { aggregateModeResolution, deriveExtensionState, projectHasBridge, projectHasOwnedConfig } from './shared';
import type { DerivedExtensionState, ExtensionToolingProject, ModeResolution } from './shared';

interface SetupRenderOptions {
    explain?: boolean;
    checkOnly?: boolean;
    shim?: string;
    /**
     * Shown on plain (flag-less) runs: composer swallows options placed before
     * "--", so this footer is both flag discovery and the safety net for a
     * swallowed flag that silently turned into a default run.
     */
    showFlagHint?: boolean;
}

type StateMap = Map<string, DerivedExtensionState>;

/**
 * "3 generated, 1 updated" answers nothing — up to 8 changes are listed by
 * path so "what did this do to my repo" is answerable from the output; larger
 * batches keep the count and defer the list to --explain.
 */
function describeFileChanges(result: SetupExtensionToolingResult): string[] {
    const changes = [
        ...result.writes
            .filter((write) => write.state === 'created')
            .map((write) => ({ verb: 'generated', file: write.file })),
        ...result.writes
            .filter((write) => write.state === 'updated')
            .map((write) => ({ verb: 'updated', file: write.file })),
        ...result.staleFiles.map((file) => ({ verb: 'removed', file })),
    ];

    if (changes.length === 0) {
        return ['Configs up to date'];
    }

    if (changes.length > 8) {
        const created = result.writes.filter((write) => write.state === 'created').length;
        const updated = result.writes.filter((write) => write.state === 'updated').length;
        const removed = result.staleFiles.length;

        return [
            [
                `${created} generated`,
                `${updated} updated`,
                ...(removed > 0 ? [`${removed} removed`] : []),
            ].join(', ') + ' (list: --explain)',
        ];
    }

    return changes.map(({ verb, file }) => `${verb}: ${file}`);
}

/**
 * The empty state must never read as a green "up to date": the most likely
 * cause is a stale var/plugins.json after installing or activating a plugin.
 */
function renderNoExtensionsFound(result: SetupExtensionToolingResult, platform: ExtensionToolingProject[]): string[] {
    const lines = [
        colors.bold('Administration extension tooling — no extensions found'),
        '',
        '  No installed extension with Administration sources was discovered.',
        '  Discovery reads var/plugins.json. If you just installed or activated a',
        '  plugin, refresh it:',
        '',
        '      bin/console bundle:dump',
    ];

    if (platform.length > 0) {
        lines.push(
            '',
            colors.dim(
                `  (Platform bundles like ${platform.map((project) => project.name).join(', ')} are always ` +
                    'covered and not listed here.)',
            ),
        );
    }

    for (const warning of result.warnings) {
        lines.push(colors.yellow(`  ⚠ ${warning}`));
    }

    return lines;
}

/** The `--shim=<name>` confirmation: what the bridge did for the named project(s) and the file-ownership tally. */
function renderShimConfirmation(result: SetupExtensionToolingResult, shim: string, stateOf: StateMap): string[] {
    const { projects } = result.manifest;
    const lines: string[] = [];
    const justBridged = projects.filter(
        (project) => (project.name === shim || project.technicalNames.includes(shim)) && projectHasBridge(project),
    );

    for (const project of justBridged) {
        if (stateOf.get(project.name) === 'bridged') {
            lines.push(
                '',
                colors.green(`✔ Bridged ${project.name}. Its tsconfig / eslint.config.mjs now extend the generated`),
                colors.green('  .shopware-admin/ bridge (git-ignored). Commit them, edit freely — keep the "extends".'),
            );
        } else {
            lines.push(
                '',
                colors.green(`✔ Bridge created for ${project.name} at .shopware-admin/ — one step left:`),
                ...describeNextStep(project)
                    .slice(1)
                    .map((line) => `  ${line}`),
            );
        }
    }

    const created = result.writes.filter((write) => write.state === 'created');
    const bridgeCreated = created.filter((write) => classifyFile(write.file) === 'bridge').length;
    const committableCreated = created.filter((write) => classifyFile(write.file) === 'committable').length;

    if (bridgeCreated > 0 || committableCreated > 0) {
        lines.push(
            colors.dim(
                `  ${bridgeCreated} git-ignored bridge file(s) in .shopware-admin/ (never commit) · ` +
                    `${committableCreated} committable plugin config(s) (commit these)`,
            ),
        );
    }

    return lines;
}

/** One line per non-empty state bucket (ready / bridged / unwired / needs-bridge / platform), preceded by a blank line. */
function renderStateSummary(
    projects: ExtensionToolingProject[],
    stateOf: StateMap,
    platform: ExtensionToolingProject[],
): string[] {
    const ready = projects.filter((project) => stateOf.get(project.name) === 'ready');
    const bridged = projects.filter((project) => stateOf.get(project.name) === 'bridged');
    const unwired = projects.filter((project) => stateOf.get(project.name) === 'bridge-unwired');
    const needsBridge = projects.filter((project) => {
        const state = stateOf.get(project.name);

        if (state === 'needs-bridge') {
            return true;
        }

        return state === 'vendor' && !projectHasBridge(project) && projectHasOwnedConfig(project);
    });
    const unverifiedBridged = bridged.some((project) =>
        project.targets.some(
            (target) =>
                (target.tsconfig !== null && !target.ts.verified) ||
                (target.eslintConfig !== null && !target.eslint.verified),
        ),
    );

    const lines = [''];

    if (ready.length > 0) {
        lines.push(`  ${colors.green('✔ ready')}    ${ready.map((project) => project.name).join(', ')}`);
    }

    if (bridged.length > 0) {
        lines.push(
            `  ${colors.cyan('● bridged')}  ${bridged.map((project) => project.name).join(', ')}  ` +
                colors.dim(
                    `(own configs compose the Shopware preset${
                        unverifiedBridged ? ' — unverified, run composer admin:check-extensions' : ''
                    })`,
                ),
        );
    }

    if (unwired.length > 0) {
        lines.push(
            `  ${colors.yellow('⚠ bridge unwired')}  ${unwired.map((project) => project.name).join(', ')}  ` +
                colors.dim("(bridge exists — own config doesn't compose it yet)"),
        );
    }

    if (needsBridge.length > 0) {
        lines.push(
            `  ${colors.yellow('● needs bridge')}   ${needsBridge.map((project) => project.name).join(', ')}  ` +
                colors.dim('(ships own config — not composed yet; bridge it to check with the preset)'),
        );
    }

    if (platform.length > 0) {
        lines.push(
            colors.dim(
                `  platform   ${platform.map((project) => project.name).join(', ')}  ` +
                    '(always checked with core tooling)',
            ),
        );
    }

    return lines;
}

/** The `--check` dry-run listing of pending writes/deletes, each tagged with its ownership class. */
function renderStaleSetup(result: SetupExtensionToolingResult): string[] {
    const lines = [
        '',
        colors.red('  Setup is stale — re-run `composer admin:setup-extension-tooling`:'),
    ];

    const classNote: Record<FileClass, string> = {
        bridge: colors.dim(' [git-ignored bridge]'),
        committable: colors.cyan(' [commit this]'),
        host: '',
    };
    const pending = result.writes.filter((entry) => entry.state === 'created' || entry.state === 'updated');

    for (const write of pending) {
        lines.push(
            colors.dim(`    would ${write.state === 'created' ? 'create' : 'update'}: ${write.file}`) +
                classNote[classifyFile(write.file)],
        );
    }

    for (const staleFile of result.staleFiles) {
        lines.push(colors.dim(`    would delete: ${staleFile}`) + classNote[classifyFile(staleFile)]);
    }

    const bridgeCount = pending.filter((write) => classifyFile(write.file) === 'bridge').length;
    const committableCount = pending.filter((write) => classifyFile(write.file) === 'committable').length;

    if (bridgeCount > 0 || committableCount > 0) {
        lines.push(
            colors.dim(
                `    (${bridgeCount} git-ignored bridge file(s), ${committableCount} committable plugin file(s), ` +
                    `${pending.length - bridgeCount - committableCount} host projection(s))`,
            ),
        );
    }

    return lines;
}

/** The "Next steps" block for extensions that still need a bridge or wiring, capped at five with an --explain overflow. */
function renderNextSteps(projects: ExtensionToolingProject[], stateOf: StateMap): string[] {
    const needsInclusion = projects.filter((project) =>
        [
            'needs-bridge',
            'bridge-unwired',
        ].includes(stateOf.get(project.name) as string),
    );

    if (needsInclusion.length === 0) {
        return [];
    }

    const lines = [
        '',
        colors.bold('  Next steps'),
    ];

    for (const project of needsInclusion.slice(0, 5)) {
        lines.push(`  ${colors.bold(project.name)}`, ...describeNextStep(project).map((step) => `    ${step}`));
    }

    if (needsInclusion.length > 5) {
        lines.push(colors.dim(`  … and ${needsInclusion.length - 5} more — run with --explain`));
    }

    return lines;
}

/** Full --explain breakdown: discovery provenance, per-target config routing with verdict source, and managed files. */
function renderExplainDetails(result: SetupExtensionToolingResult, projects: ExtensionToolingProject[]): string[] {
    const lines = [
        '',
        colors.bold('  Details'),
        `    Administration root: ${result.manifest.adminRoot}`,
        `    Discovered from: ${result.discoverySource.path}${
            result.discoverySource.updatedAt ? ` (updated ${result.discoverySource.updatedAt})` : ''
        }`,
        `    Entity schema: ${
            result.manifest.entitySchemaAvailable ? 'available' : 'stub (run composer admin:generate-entity-schema-types)'
        }`,
    ];

    // Setup never runs live probes (the check does), so a verified verdict
    // here was carried in from the probe cache and an unverified one is fresh
    // static analysis — surface which, so a reader knows how much to trust it.
    const verdictSource = (resolution: ModeResolution, hasOwnConfig: boolean): string => {
        if (!hasOwnConfig) {
            return '';
        }

        return resolution.verified ? ' (cached-live)' : ' (static)';
    };

    for (const project of projects) {
        const moduleCount = project.technicalNames.length;
        const ts = aggregateModeResolution(project, 'ts');
        const eslint = aggregateModeResolution(project, 'eslint');

        lines.push(
            `    - ${project.name} · ${moduleCount === 1 ? '1 module' : `${moduleCount} modules`} · ` +
                `ts:${ts.mode} · eslint:${eslint.mode}`,
        );

        for (const target of project.targets) {
            lines.push(
                `        ${target.technicalNames.join(', ')} · ${target.sourcePath}`,
                `          runtime: ${target.ts.mode}${verdictSource(target.ts, target.tsconfig !== null)} → ${target.checkTsconfig}`,
                `          specs:   ${target.specTsconfig}`,
                `          eslint:  ${target.eslint.mode}${verdictSource(target.eslint, target.eslintConfig !== null)} → ${target.eslintConfig ?? 'generated root config'}`,
            );
        }
    }

    lines.push(
        '',
        '    Own path aliases: declare them in tsconfig.aliases.json next to the plugin config,',
        '    e.g. { "MyPlugin/*": ["src/*"] } — the generated .shopware-admin/ bridge merges them',
        '    with the preset paths (tsconfig "paths" cannot be extended additively).',
        '',
        '    Managed files:',
        ...result.writes.map((write) => `      ${write.state}: ${write.file}`),
        ...result.staleFiles.map((file) => `      removed: ${file}`),
    );

    for (const instruction of result.instructions) {
        lines.push('', ...instruction.split('\n').map((line) => `    ${line}`));
    }

    return lines;
}

export function renderSetupReport(result: SetupExtensionToolingResult, options: SetupRenderOptions = {}): string {
    const { projects } = result.manifest;
    const stateOf: StateMap = new Map(
        projects.map((project) => [
            project.name,
            deriveExtensionState(project),
        ]),
    );
    const platform = projects.filter((project) => stateOf.get(project.name) === 'platform');
    const ownExtensions = projects.filter((project) => stateOf.get(project.name) !== 'platform');

    if (ownExtensions.length === 0) {
        return renderNoExtensionsFound(result, platform).join('\n');
    }

    const lines = [colors.bold(`Administration extension tooling — ${ownExtensions.length} extension(s)`)];

    if (options.shim) {
        lines.push(...renderShimConfirmation(result, options.shim, stateOf));
    }

    lines.push(...renderStateSummary(projects, stateOf, platform));

    lines.push('', ...describeFileChanges(result).map((line) => `  ${colors.dim(line)}`));

    if (result.manifest.rootConfigs.tsconfig === 'conflict') {
        lines.push(colors.yellow('  ⚠ root tsconfig.json is user-owned — run with --explain to integrate'));
    }

    if (result.manifest.rootConfigs.eslintConfig === 'conflict') {
        lines.push(colors.yellow('  ⚠ root eslint.config.mjs is user-owned — run with --explain to integrate'));
    }

    for (const warning of result.warnings) {
        lines.push(colors.yellow(`  ⚠ ${warning}`));
    }

    if (options.checkOnly && result.changed) {
        lines.push(...renderStaleSetup(result));
    }

    lines.push(...renderNextSteps(projects, stateOf));

    if (!options.explain) {
        lines.push('', colors.dim('  IDE setup: run with --explain for VS Code / Zed / PhpStorm config'));
    }

    if (options.showFlagHint) {
        lines.push(
            colors.dim(
                '  Options need "--": composer admin:setup-extension-tooling -- --check | --explain | --shim=<name> | --help',
            ),
        );
    }

    if (options.explain) {
        lines.push(...renderExplainDetails(result, projects));
    }

    return lines.join('\n');
}
