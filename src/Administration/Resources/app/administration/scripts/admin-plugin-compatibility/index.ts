/**
 * @sw-package framework
 */
/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { EXIT_CODES } from './constants';
import { parseCliArguments } from './cli';
import { runCompatibilityWorkflow } from './runner';

type Writable = {
    write: (message: string) => void;
};

type CliIo = {
    stdout: Writable;
    stderr: Writable;
};

const DEFAULT_IO: CliIo = {
    stdout: process.stdout,
    stderr: process.stderr,
};

export async function runCli(argv = process.argv.slice(2), env = process.env, io = DEFAULT_IO): Promise<number> {
    const parsed = parseCliArguments(argv);

    if (parsed.type === 'help') {
        io.stdout.write(`${parsed.help}\n`);

        return EXIT_CODES.success;
    }

    if (parsed.type === 'error') {
        io.stderr.write(`${parsed.message}\n\n${parsed.help}\n`);

        return EXIT_CODES.usage;
    }

    const result = await runCompatibilityWorkflow(parsed.options, { env });

    if (result.status === 'failed') {
        io.stderr.write(`${JSON.stringify(result, null, 2)}\n`);

        return result.exitCode;
    }

    io.stdout.write(`${JSON.stringify(result, null, 2)}\n`);

    return EXIT_CODES.success;
}

if (require.main === module) {
    void runCli().then((exitCode) => {
        process.exitCode = exitCode;
    });
}
