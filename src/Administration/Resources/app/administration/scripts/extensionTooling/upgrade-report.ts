/**
 * @sw-package framework
 *
 * Human-readable rendering for `admin:upgrade-extensions`. Pure: takes the run
 * result and returns the report string, colored via picocolors.
 */

import colors from 'picocolors';
import type { ToolingCommands } from './shared';
import { availableTargets } from './upgrade-codemods';
import type { Codemod } from './upgrade-codemods';
import type { CodemodReport, ExtensionUpgrade, UpgradeExtensionsResult } from './upgrade';

const TITLE_WIDTH = 36;

function plural(count: number, singular: string, pluralForm = `${singular}s`): string {
    return `${count} ${count === 1 ? singular : pluralForm}`;
}

function renderCodemodRow(report: CodemodReport, write: boolean): string {
    const { codemod, changes, files, manual } = report;
    const title = codemod.title.padEnd(TITLE_WIDTH, ' ');
    const manualNote = manual.length > 0 ? `${plural(manual.length, 'spot needs', 'spots need')} manual work` : '';

    if (!report.enabled) {
        const detail =
            changes > 0
                ? `${plural(changes, 'component')} can be converted, add --with=${codemod.id}`
                : `opt-in, nothing to convert`;

        return `  ${colors.dim('○')} ${title}${colors.dim(detail)}`;
    }

    if (changes === 0 && manual.length === 0) {
        return `  ${colors.dim('·')} ${title}${colors.dim('nothing to change')}`;
    }

    let detail: string;

    if (codemod.kind === 'sfc-migration') {
        detail = write
            ? `${plural(changes, '.vue draft')} written, originals still registered`
            : `${plural(changes, 'component')} can be converted`;
    } else {
        detail = changes > 0 ? `${plural(changes, 'change')} in ${plural(files, 'file')}` : '';
    }

    const parts = [detail, manualNote].filter((part) => part !== '').join(', ');

    return manual.length > 0 ? `  ${colors.yellow('!')} ${title}${parts}` : `  ${colors.green('✔')} ${title}${parts}`;
}

function renderExtension(extension: ExtensionUpgrade, write: boolean): string[] {
    const lines = [`${colors.bold(extension.name)}  ${colors.dim(extension.basePath)}`];

    if (extension.skipped) {
        lines.push(colors.dim(`  skipped: ${extension.skipped}`));

        return lines;
    }

    lines.push(...extension.codemods.map((report) => renderCodemodRow(report, write)));

    const manual = extension.codemods.filter((report) => report.enabled).flatMap((report) => report.manual);

    if (manual.length > 0) {
        lines.push('', colors.yellow('  Needs manual work:'));

        for (const spot of manual) {
            const location = spot.line === undefined ? spot.file : `${spot.file}:${spot.line}:${spot.column ?? 1}`;

            lines.push(`    ${colors.dim(location)}  ${spot.message}`);
        }
    }

    for (const warning of extension.warnings) {
        lines.push(colors.yellow(`  ! ${warning}`));
    }

    for (const error of extension.errors) {
        lines.push(colors.red(`  ✖ ${error}`));
    }

    return lines;
}

function commandArguments(result: UpgradeExtensionsResult, names: string[], write: boolean): string {
    return [
        `--only=${names.join(',')}`,
        `--target=${result.target}`,
        ...(result.optIns.length > 0 ? [`--with=${result.optIns.join(',')}`] : []),
        ...(write ? ['--write'] : []),
    ].join(' ');
}

export function renderUpgradeReport(result: UpgradeExtensionsResult, commands: ToolingCommands): string {
    const mode = result.write ? 'writing' : 'dry run';
    const lines = [colors.dim(`Administration codemods · target ${result.target} · ${mode}`), ''];

    if (result.fatalDiagnostics.length > 0) {
        lines.push(...result.fatalDiagnostics.map((diagnostic) => colors.red(`✖ ${diagnostic}`)));

        return lines.join('\n');
    }

    const upgraded = result.extensions.filter((extension) => !extension.skipped);

    if (result.extensions.length === 0) {
        lines.push('No writable Administration extensions discovered.');

        return lines.join('\n');
    }

    for (const extension of result.extensions) {
        lines.push(...renderExtension(extension, result.write), '');
    }

    const applied = upgraded.flatMap((extension) => extension.codemods.filter((report) => report.enabled));
    const changes = applied.reduce((sum, report) => sum + report.changes, 0);
    const files = upgraded.reduce((sum, extension) => sum + extension.changedFiles.length, 0);
    const manual = applied.reduce((sum, report) => sum + report.manual.length, 0);
    const names = upgraded.map((extension) => extension.name);

    if (changes === 0) {
        lines.push(
            colors.cyan(
                manual > 0
                    ? `Nothing to rewrite automatically for ${result.target}. Fix the spots above by hand.`
                    : `Nothing to change for ${result.target}.`,
            ),
        );

        return lines.join('\n');
    }

    if (!result.write) {
        lines.push(
            colors.cyan('Dry run, nothing was written.'),
            colors.dim(`Apply with: ${commands.upgrade} -- ${commandArguments(result, names, true)}`),
        );

        return lines.join('\n');
    }

    lines.push(
        colors.cyan(`Wrote ${plural(changes, 'change')} in ${plural(files, 'file')}.`),
        colors.dim('Review them with: git diff'),
        colors.dim(`Next: ${commands.check} -- --only=${names.join(',')}`),
    );

    return lines.join('\n');
}

export function renderCodemodList(codemods: Codemod[]): string {
    const lines: string[] = [];

    for (const target of availableTargets(codemods)) {
        lines.push(colors.bold(`Shopware ${target}`));

        for (const codemod of codemods.filter((candidate) => candidate.target === target)) {
            const note = codemod.optIn ? colors.dim(`opt-in, --with=${codemod.id}`) : colors.dim(codemod.id);

            lines.push(`  ${codemod.title.padEnd(TITLE_WIDTH, ' ')}${note}`);
        }

        lines.push('');
    }

    lines.push(colors.dim('A run for a target also applies the codemods of every earlier target.'));

    return lines.join('\n');
}
