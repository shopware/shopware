/**
 * @sw-package framework
 *
 * CLI entry point of the Administration extension checker. Owns the argument
 * semantics and the exit-code contract; everything else lives in discovery,
 * runner and report.
 */

import path from 'path';
import { parseArgs } from 'util';
import { discoverAdminRoots, selectRoots } from './discovery';
import { runCheck } from './runner';
import { EXIT_FINDINGS, EXIT_OK, EXIT_TOOL_ERROR, EXIT_USAGE, exitCodeFor, renderReport } from './report';
import { relativePosix } from './shared';
import type { AdminRoot, CheckReport } from './shared';
import type { DiscoveryOptions } from './discovery';
import type { RunnerOptions } from './runner';

const USAGE = `Usage: administration:extension:check [<Name>...] [options]

Type-checks and lints every installed Administration extension against this
installation. Nothing is written into an extension; the programs the tools run
against are generated below var/admin-extension-tooling/.

Arguments:
  <Name>...            Extension, bundle, or technical name. Repeatable.
                       Defaults to every installed extension. A named root is
                       always checked, platform or not.

Options:
  --types              Only type-check.
  --lint               Only lint. Without --types and --lint, both run.
  --fix                Apply ESLint's fixes. Requires linting.
  --include-platform   Also check the platform bundles.
  -h, --help           Show this help.

Exit codes:
  0  no findings, at least one file checked
  1  type or lint findings
  2  usage error
  3  tool error (missing bundle configuration, missing binary, zero files checked)
`;

export interface CliIo {
    out: (text: string) => void;
    err: (text: string) => void;
}

export interface CliDependencies {
    io: CliIo;
    discover: (options: DiscoveryOptions) => AdminRoot[];
    run: (options: RunnerOptions) => CheckReport;
    projectRoot: string;
    administrationRoot: string;
}

function defaultProjectRoot(): string {
    return path.resolve(process.env.PROJECT_ROOT ?? process.cwd());
}

function defaultAdministrationRoot(): string {
    return path.resolve(__dirname, '..', '..');
}

export function runCli(argv: string[], dependencies: Partial<CliDependencies> = {}): number {
    const io: CliIo = dependencies.io ?? {
        out: (text) => process.stdout.write(text),
        err: (text) => process.stderr.write(text),
    };
    const discover = dependencies.discover ?? discoverAdminRoots;
    const run = dependencies.run ?? runCheck;
    const projectRoot = dependencies.projectRoot ?? defaultProjectRoot();
    const administrationRoot = dependencies.administrationRoot ?? defaultAdministrationRoot();

    let names: string[];
    let flags: { types?: boolean; lint?: boolean; fix?: boolean; 'include-platform'?: boolean; help?: boolean };

    try {
        const parsed = parseArgs({
            args: argv,
            allowPositionals: true,
            strict: true,
            options: {
                types: { type: 'boolean' },
                lint: { type: 'boolean' },
                fix: { type: 'boolean' },
                'include-platform': { type: 'boolean' },
                help: { type: 'boolean', short: 'h' },
            },
        });

        names = parsed.positionals;
        flags = parsed.values;
    } catch (error) {
        io.err(`${(error as Error).message}\n\n${USAGE}`);

        return EXIT_USAGE;
    }

    if (flags.help === true) {
        io.out(USAGE);

        return EXIT_OK;
    }

    const explicitTools = flags.types === true || flags.lint === true;
    const types = explicitTools ? flags.types === true : true;
    const lint = explicitTools ? flags.lint === true : true;
    const fix = flags.fix === true;

    if (fix && !lint) {
        io.err(`--fix applies ESLint's fixes and therefore requires linting; drop --types or drop --fix.\n\n${USAGE}`);

        return EXIT_USAGE;
    }

    let roots: AdminRoot[];

    try {
        roots = discover({
            projectRoot,
            administrationRoot,
            pluginsConfigPath: path.join(projectRoot, 'var', 'plugins.json'),
        });
    } catch (error) {
        io.err(`tool error: ${(error as Error).message}\n`);

        return EXIT_TOOL_ERROR;
    }

    const selection = selectRoots(roots, { names, includePlatform: flags['include-platform'] === true });

    if (selection.unknownNames.length > 0) {
        const known = [...new Set(roots.map((root) => root.extensionName))].sort().join(', ');

        io.err(
            `Unknown extension: ${selection.unknownNames.join(', ')}.\n` +
                `Installed extensions with Administration sources: ${known === '' ? '<none>' : known}\n`,
        );

        return EXIT_USAGE;
    }

    const report = run({ projectRoot, administrationRoot, roots: selection.selected, types, lint, fix });
    const notices =
        selection.skippedPlatform.length > 0
            ? [
                  `Skipped ${selection.skippedPlatform.length} platform source roots. Pass --include-platform to check them too.`,
              ]
            : [];

    io.out(
        renderReport(report, {
            notices,
            sourcePaths: Object.fromEntries(
                selection.selected.map((root) => [
                    root.slug,
                    relativePosix(projectRoot, root.sourcePath),
                ]),
            ),
        }),
    );

    return exitCodeFor(report);
}

/* istanbul ignore next -- entry point */
if (require.main === module) {
    process.exit(runCli(process.argv.slice(2)));
}

export { EXIT_FINDINGS, EXIT_OK, EXIT_TOOL_ERROR, EXIT_USAGE };
