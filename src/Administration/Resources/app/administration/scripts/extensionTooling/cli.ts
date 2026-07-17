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
    /** Parsed but hidden from help (wrapper-script plumbing like --project-root). */
    internal?: boolean;
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

function levenshtein(left: string, right: string): number {
    const distances = Array.from({ length: left.length + 1 }, (unused, index) => index);

    for (let rightIndex = 1; rightIndex <= right.length; rightIndex += 1) {
        let previousDiagonal = distances[0];

        distances[0] = rightIndex;

        for (let leftIndex = 1; leftIndex <= left.length; leftIndex += 1) {
            const previousValue = distances[leftIndex];

            distances[leftIndex] = Math.min(
                distances[leftIndex] + 1,
                distances[leftIndex - 1] + 1,
                previousDiagonal + (left[leftIndex - 1] === right[rightIndex - 1] ? 0 : 1),
            );
            previousDiagonal = previousValue;
        }
    }

    return distances[left.length];
}

function closestFlag(name: string, spec: CommandSpec): string | null {
    let best: { flag: string; distance: number } | null = null;

    for (const flag of spec.flags) {
        const distance = levenshtein(name, flag.name);

        if (distance <= 2 && (best === null || distance < best.distance)) {
            best = { flag: flag.name, distance };
        }
    }

    return best?.flag ?? null;
}

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
            const suggestion = closestFlag(name, spec);

            throw new CliUsageError(`Unknown option ${name}.${suggestion ? ` Did you mean ${suggestion}?` : ''}`);
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

export function renderHelp(spec: CommandSpec): string {
    const visibleFlags = spec.flags.filter((flag) => !flag.internal);
    const usageEntries = visibleFlags.map((flag) =>
        flag.value === 'required' ? `${flag.name}=${flag.valueName ?? '<value>'}` : flag.name,
    );
    const optionRows: Array<[string, string]> = [
        ...visibleFlags.map((flag): [string, string] => [
            flag.value === 'required' ? `${flag.name}=${flag.valueName ?? '<value>'}` : flag.name,
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
