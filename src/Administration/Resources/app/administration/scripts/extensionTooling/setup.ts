/**
 * @sw-package framework
 *
 * CLI entry point of the Administration extension setup: prepares editor
 * resolvability. Separate from the checker on purpose — a check must not write
 * into the project root, least of all in CI.
 */

import path from 'path';
import { parseArgs } from 'util';
import { buildFarm } from './resolution';
import { EXIT_OK, EXIT_TOOL_ERROR, EXIT_USAGE, exitCodeForFarm, renderFarmReport } from './report';
import { relativePosix } from './shared';
import type { FarmResult } from './resolution';
import type { CliIo } from './cli';

const USAGE = `Usage: administration:extension:setup [options]

Links the installed Administration into <projectRoot>/node_modules, so every
extension in this installation resolves the Administration sources, its type
surface and every host package — in the editor, with no config file and no IDE
settings.

The directory is replaced on every run, never merged, and ignores itself via a
.gitignore holding "*". Re-run it after an "npm ci" in the Administration.

Options:
  -h, --help   Show this help.

Exit codes:
  0  the links are in place (individual failures are reported)
  2  usage error
  3  tool error (missing Administration dependencies, a foreign node_modules, nothing linked)
`;

export interface SetupDependencies {
    io: CliIo;
    build: (projectRoot: string, administrationRoot: string) => FarmResult;
    projectRoot: string;
    administrationRoot: string;
}

export function runSetup(argv: string[], dependencies: Partial<SetupDependencies> = {}): number {
    const io: CliIo = dependencies.io ?? {
        out: (text) => process.stdout.write(text),
        err: (text) => process.stderr.write(text),
    };
    const build = dependencies.build ?? buildFarm;
    const projectRoot = path.resolve(dependencies.projectRoot ?? process.env.PROJECT_ROOT ?? process.cwd());
    const administrationRoot = dependencies.administrationRoot ?? path.resolve(__dirname, '..', '..');

    try {
        const parsed = parseArgs({
            args: argv,
            allowPositionals: false,
            strict: true,
            options: { help: { type: 'boolean', short: 'h' } },
        });

        if (parsed.values.help === true) {
            io.out(USAGE);

            return EXIT_OK;
        }
    } catch (error) {
        io.err(`${(error as Error).message}\n\n${USAGE}`);

        return EXIT_USAGE;
    }

    const result = build(projectRoot, administrationRoot);
    const output = renderFarmReport(result, relativePosix(projectRoot, result.farmPath));

    if (exitCodeForFarm(result) === EXIT_TOOL_ERROR) {
        io.err(output);
    } else {
        io.out(output);
    }

    return exitCodeForFarm(result);
}

/* istanbul ignore next -- entry point */
if (require.main === module) {
    process.exit(runSetup(process.argv.slice(2)));
}
