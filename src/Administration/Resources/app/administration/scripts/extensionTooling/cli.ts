/**
 * @sw-package framework
 *
 * Strict CLI argument layer for the extension tooling commands. Node's
 * `parseArgs` does the parsing; this module owns the command spec that drives
 * both it and `--help`, and normalizes its errors into one usage error. Parsing
 * stays pure (no filesystem or environment access) so `--help` and usage errors
 * work before any project state is touched, and unknown flags are rejected
 * instead of silently ignored — a mistyped `--chekc` must never degrade into a
 * mutating default run.
 */

import { parseArgs } from 'node:util';

interface FlagSpec {
    /** Long option name including dashes, e.g. "--check". */
    name: string;
    /** 'required' when the flag is only meaningful with an `=value`. */
    value?: 'required';
    /** Placeholder rendered in help and errors, e.g. "<Extension>:<dir>". */
    valueName?: string;
    description: string;
}

export interface CommandSpec {
    /** Composer command name, e.g. "admin:setup-extension-tooling". */
    command: string;
    description: string;
    flags: FlagSpec[];
}

interface ParsedArgs {
    /** Values of `--name=value` options, keyed by flag name including dashes. */
    values: Record<string, string | undefined>;
    /** Boolean flags that were present, by flag name including dashes. */
    flags: Set<string>;
    help: boolean;
}

/** Exit code 2 territory: the invocation itself is wrong, nothing was executed. */
export class CliUsageError extends Error {}

export function parseCli(argv: string[], spec: CommandSpec): ParsedArgs {
    // Help wins over everything else: a user asking for help must get it even
    // when the rest of the invocation would not validate.
    if (argv.includes('--help') || argv.includes('-h')) {
        return { values: {}, flags: new Set(), help: true };
    }

    let parsed;

    try {
        parsed = parseArgs({
            args: argv,
            options: Object.fromEntries(
                spec.flags.map((flag) => [
                    flag.name.replace(/^--/, ''),
                    { type: flag.value === 'required' ? ('string' as const) : ('boolean' as const) },
                ]),
            ),
            strict: true,
            allowPositionals: false,
        });
    } catch (error) {
        // Node prefixes its parseArgs errors with `TypeError [ERR_PARSE_ARGS_…]:`
        // and quotes the offending token; strip both so the usage line reads
        // like the rest of the tooling's output.
        const message = (error instanceof Error ? error.message : String(error))
            .replace(/^\w*Error \[[A-Z_]+\]:\s*/, '')
            .replace(/'/g, '');

        throw new CliUsageError(`${message}. See --help for the available options.`);
    }

    const values: Record<string, string | undefined> = {};
    const flags = new Set<string>();

    for (const flag of spec.flags) {
        const value = parsed.values[flag.name.replace(/^--/, '')];

        if (value === undefined) {
            continue;
        }

        if (typeof value === 'boolean') {
            flags.add(flag.name);

            continue;
        }

        // parseArgs accepts `--name=` as an empty string; the flags that take a
        // value are never meaningful without one.
        if (value === '') {
            throw new CliUsageError(`${flag.name} requires a value: ${optionLabel(flag)}`);
        }

        values[flag.name] = value;
    }

    return { values, flags, help: false };
}

function optionLabel(flag: FlagSpec): string {
    return flag.value === 'required' ? `${flag.name}=${flag.valueName ?? '<value>'}` : flag.name;
}

export function renderHelp(spec: CommandSpec): string {
    const usageEntries = spec.flags.map(optionLabel);
    const optionRows: Array<[string, string]> = [
        ...spec.flags.map((flag): [string, string] => [
            optionLabel(flag),
            flag.description,
        ]),
        [
            '-h, --help',
            'This help.',
        ],
    ];
    const optionWidth = Math.max(...optionRows.map(([option]) => option.length));

    return [
        spec.description,
        '',
        'Usage (note the "--" — composer swallows options placed before it):',
        `  composer ${spec.command} -- [options]`,
        '',
        'Options:',
        ...optionRows.map(
            ([
                option,
                description,
            ]) => `  ${option.padEnd(optionWidth, ' ')}  ${description}`,
        ),
        '',
        `Example: composer ${spec.command} -- ${usageEntries[0] ?? '--help'}`,
    ].join('\n');
}
