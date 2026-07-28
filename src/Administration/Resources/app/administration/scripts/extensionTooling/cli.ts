/**
 * @sw-package framework
 *
 * Strict CLI argument layer for the extension tooling commands. Parsing is
 * pure (no filesystem or environment access) so `--help` and usage errors
 * work before any project state is touched. Unknown flags are rejected
 * instead of silently ignored — a mistyped `--chekc` must never degrade
 * into a mutating default run.
 */

export interface FlagSpec {
    /** Long option name including dashes, e.g. "--check". */
    name: string;
    /** 'required' when the flag is only meaningful with an `=value`. */
    value?: 'required';
    /** Placeholder rendered in help and errors, e.g. "<TechnicalName>|all-custom". */
    valueName?: string;
    description: string;
}

export interface CommandSpec {
    /** Composer command name, e.g. "admin:setup-extension-tooling". */
    command: string;
    description: string;
    flags: FlagSpec[];
}

export interface ParsedArgs {
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

    const values: Record<string, string | undefined> = {};
    const flags = new Set<string>();

    for (const argument of argv) {
        if (!argument.startsWith('--')) {
            throw new CliUsageError(`Unexpected argument "${argument}" — options start with "--".`);
        }

        const equalsIndex = argument.indexOf('=');
        const name = equalsIndex === -1 ? argument : argument.slice(0, equalsIndex);
        const flagSpec = spec.flags.find((flag) => flag.name === name);

        if (!flagSpec) {
            throw new CliUsageError(`Unknown option ${name}. See --help for the available options.`);
        }

        if (flagSpec.value === 'required') {
            const value = equalsIndex === -1 ? '' : argument.slice(equalsIndex + 1);

            if (value === '') {
                throw new CliUsageError(`${name} requires a value: ${name}=${flagSpec.valueName ?? '<value>'}`);
            }

            values[name] = value;
        } else {
            if (equalsIndex !== -1) {
                throw new CliUsageError(`${name} does not take a value.`);
            }

            flags.add(name);
        }
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
