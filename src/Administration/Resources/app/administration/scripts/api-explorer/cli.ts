/**
 * @sw-package framework
 */

import { diffCommand } from './commands/diff';
import { reportCommand } from './commands/report';
import { serveCommand } from './commands/serve';
import { snapshotCommand } from './commands/snapshot';
import { EXIT_RUNTIME_ERROR, EXIT_USAGE_ERROR, UsageError, type CliCommand, type CliIo } from './commands/types';

const COMMANDS: Record<string, CliCommand> = {
    snapshot: snapshotCommand,
    diff: diffCommand,
    serve: serveCommand,
    report: reportCommand,
};

const PROGRAM = 'api-explorer';

function generalUsage(): string {
    const commands = Object.entries(COMMANDS)
        .map(
            ([
                name,
                command,
            ]) => `  ${name.padEnd(10)} ${command.summary}`,
        )
        .join('\n');

    return `Usage: ${PROGRAM} <command> [options]\n\nCommands:\n${commands}\n`;
}

function isParseArgsError(error: unknown): boolean {
    return String((error as NodeJS.ErrnoException | undefined)?.code).startsWith('ERR_PARSE_ARGS_');
}

const processIo: CliIo = {
    stdout: (text) => process.stdout.write(text),
    stderr: (text) => process.stderr.write(text),
};

export async function runCli(argv: string[], io: CliIo = processIo): Promise<number> {
    const [
        commandName,
        ...args
    ] = argv;
    const command = commandName !== undefined && Object.hasOwn(COMMANDS, commandName) ? COMMANDS[commandName] : undefined;

    if (!command) {
        io.stderr(commandName === undefined ? generalUsage() : `Unknown command: ${commandName}\n\n${generalUsage()}`);
        return EXIT_USAGE_ERROR;
    }

    try {
        return await command.run(args, io);
    } catch (error) {
        if (error instanceof UsageError || isParseArgsError(error)) {
            io.stderr(`${(error as Error).message}\n\nUsage: ${PROGRAM} ${command.usage}\n`);
            return EXIT_USAGE_ERROR;
        }

        io.stderr(`${error instanceof Error ? (error.stack ?? error.message) : String(error)}\n`);
        return EXIT_RUNTIME_ERROR;
    }
}

if (require.main === module) {
    void runCli(process.argv.slice(2)).then((exitCode) => {
        process.exitCode = exitCode;
    });
}
